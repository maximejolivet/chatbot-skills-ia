<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\AiProvider\Client\ChatMessage;
use App\AiProvider\Client\CompletionResult;
use App\AiProvider\Client\LlmClientInterface;
use App\AiProvider\ProviderSelectionService;
use App\Chat\ChatOrchestrationService;
use App\Chat\ChatReplyResult;
use App\Chat\RagContextService;
use App\Entity\AiAgent;
use App\Entity\Collection;
use App\Entity\Workflow;
use App\Enum\WorkflowStatus;
use App\KnowledgeBase\CollectionService;
use App\Repository\AiProviderConfigRepository;
use App\Repository\WorkflowRepository;
use App\Repository\WorkflowStepRepository;
use App\VectorConnector\VectorSearchService;
use App\Workflow\WorkflowExecutionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Controllable LlmClientInterface double -- ProviderSelectionService is
 * `final` with no seam to make it resolve to a fake client (see
 * ChatOrchestrationService::orchestrate()'s docblock), so tests reach the
 * orchestration logic via reflection instead, bypassing that resolution
 * entirely and handing it this directly.
 */
final class FakeLlmClient implements LlmClientInterface
{
    /** @var string[] */
    public array $streamed = [];

    /**
     * @param string[] $streamChunks
     */
    public function __construct(
        private readonly ?CompletionResult $completionResult = null,
        private readonly array $streamChunks = [],
    ) {}

    public function complete(array $messages, ?array $tools = null, float $temperature = 0.7, int $maxTokens = 3000): CompletionResult
    {
        return $this->completionResult ?? new CompletionResult(new ChatMessage(role: 'assistant', content: ''), []);
    }

    public function stream(array $messages, float $temperature = 0.7, int $maxTokens = 3000): iterable
    {
        foreach ($this->streamChunks as $chunk) {
            $this->streamed[] = $chunk;
            yield $chunk;
        }
    }

    public function checkStatus(): array
    {
        return ['status' => 'running'];
    }
}

/**
 * ChatOrchestrationService's other two collaborators (RagContextService,
 * WorkflowExecutionService) are also `final`, but each wraps a non-final
 * seam (VectorSearchService/CollectionService, WorkflowRepository/etc.) --
 * built as real instances here, stubbed at those seams, same technique as
 * VectorSearchServiceTest/WorkflowExecutionServiceTest.
 */
final class ChatOrchestrationServiceTest extends TestCase
{
    /**
     * @param array<int, array<string, mixed>> $ragResults
     */
    private function service(array $ragResults = []): ChatOrchestrationService
    {
        $providerSelection = new ProviderSelectionService(
            $this->createStub(AiProviderConfigRepository::class),
            $this->createStub(LoggerInterface::class),
            'ollama',
            '',
            '',
            '',
            30,
            'http://ollama.test:11434',
            'chat-model',
            'embed-model',
            'analysis-model',
        );

        $vectorSearchService = $this->createStub(VectorSearchService::class);
        $vectorSearchService->method('search')->willReturn($ragResults);
        $commonCollection = $this->createStub(Collection::class);
        $commonCollection->method('getCollectionNameForQdrant')->willReturn('common_qdrant');
        $collectionService = $this->createStub(CollectionService::class);
        $collectionService->method('ensureCommonCollection')->willReturn($commonCollection);
        $ragContextService = new RagContextService(
            $collectionService,
            $vectorSearchService,
            $this->createStub(LoggerInterface::class),
        );

        $workflowExecutionService = new WorkflowExecutionService(
            $this->createStub(WorkflowRepository::class),
            $this->createStub(WorkflowStepRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(MailerInterface::class),
            'test@example.com',
        );

        return new ChatOrchestrationService($providerSelection, $ragContextService, $workflowExecutionService);
    }

    /**
     * @param ChatMessage[] $history
     */
    private function orchestrate(
        ChatOrchestrationService $service,
        LlmClientInterface $llmClient,
        string $userMessage = 'Bonjour',
        array $history = [],
        ?AiAgent $agent = null,
        ?callable $onDelta = null,
    ): ChatReplyResult {
        return (new \ReflectionMethod($service, 'orchestrate'))
            ->invoke($service, $llmClient, $userMessage, $history, $agent, null, $onDelta);
    }

    public function testNoAgentAndOnDeltaStreamsIncrementally(): void
    {
        $client = new FakeLlmClient(streamChunks: ['Bon', 'jour', ' !']);
        $deltas = [];

        $result = $this->orchestrate(
            $this->service(),
            $client,
            onDelta: function (string $chunk) use (&$deltas): void {
                $deltas[] = $chunk;
            },
        );

        self::assertSame(['Bon', 'jour', ' !'], $deltas);
        self::assertSame(['Bon', 'jour', ' !'], $client->streamed);
        self::assertSame('Bonjour !', $result->content);
        self::assertSame('estimated', $result->usage['source']);
    }

    public function testNoAgentWithoutOnDeltaUsesTheBufferedPath(): void
    {
        $completion = new CompletionResult(
            new ChatMessage(role: 'assistant', content: 'Réponse bufferisée'),
            ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8, 'source' => 'provider', 'provider' => 'ollama', 'model' => 'chat-model'],
        );
        $client = new FakeLlmClient(completionResult: $completion, streamChunks: ['should', 'not', 'be', 'used']);

        $result = $this->orchestrate($this->service(), $client);

        self::assertSame([], $client->streamed);
        self::assertSame('Réponse bufferisée', $result->content);
        self::assertSame('provider', $result->usage['source']);
    }

    public function testStreamingPathStillReturnsRagSources(): void
    {
        $client = new FakeLlmClient(streamChunks: ['Réponse']);

        $result = $this->orchestrate(
            $this->service(ragResults: [['document_id' => 1, 'document_title' => 'CV', 'score' => 0.9]]),
            $client,
            onDelta: static function (): void {},
        );

        self::assertSame([['document_id' => 1, 'document_title' => 'CV', 'score' => 0.9]], $result->sources);
    }

    public function testAgentWithActiveWorkflowSkipsStreamingEvenWithOnDelta(): void
    {
        $workflow = new Workflow()->setName('planifier_entretien')->setStatus(WorkflowStatus::Active);
        $agent = new AiAgent();
        $agent->addWorkflow($workflow);
        // CollectionService::getQdrantCollectionNameForAgent() takes a strict
        // int, but this agent is never persisted -- give it an id the same
        // way RagContextServiceTest does, via reflection on the otherwise
        // Doctrine-managed private property.
        new \ReflectionProperty(AiAgent::class, 'id')->setValue($agent, 1);

        $completion = new CompletionResult(
            new ChatMessage(role: 'assistant', content: 'Pas besoin d\'outil cette fois'),
            ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2, 'source' => 'provider', 'provider' => 'ollama', 'model' => 'chat-model'],
        );
        $client = new FakeLlmClient(completionResult: $completion, streamChunks: ['should', 'not', 'stream']);
        $deltas = [];

        $result = $this->orchestrate(
            $this->service(),
            $client,
            agent: $agent,
            onDelta: function (string $chunk) use (&$deltas): void {
                $deltas[] = $chunk;
            },
        );

        // The buffered path (agent has a tool available), so stream() is
        // never called -- but onDelta still fires exactly once, with the
        // full content, so ConversationStreamController gets a uniform
        // "zero or more deltas, then done" contract either way.
        self::assertSame([], $client->streamed);
        self::assertSame(['Pas besoin d\'outil cette fois'], $deltas);
        self::assertSame('Pas besoin d\'outil cette fois', $result->content);
    }
}
