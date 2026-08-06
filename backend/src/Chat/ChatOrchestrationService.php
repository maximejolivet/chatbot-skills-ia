<?php

namespace App\Chat;

use App\AiProvider\Client\ChatMessage;
use App\AiProvider\Client\ToolCall;
use App\AiProvider\Client\ToolSpec;
use App\AiProvider\ProviderSelectionService;
use App\Entity\AiAgent;
use App\Entity\Conversation;
use App\Entity\Workflow;
use App\Enum\AiProviderUsage;
use App\Workflow\WorkflowExecutionService;

/**
 * The real tool-calling loop: asks the LLM for a completion, and if the model
 * requests a tool call, executes the matching Workflow synchronously via
 * WorkflowExecutionService, feeds the result back, and asks again -- up to
 * MAX_TOOL_ITERATIONS times -- before returning the model's final
 * natural-language answer.
 *
 * RAG (vector search) is a separate, explicit orchestration step here rather
 * than being baked into the LLM client itself.
 */
final class ChatOrchestrationService
{
    public const DEFAULT_SYSTEM_PROMPT = <<<'PROMPT'
        Tu es un assistant IA utile et bienveillant spécialisé dans l'aide aux utilisateurs.
        Tu réponds en français de manière claire et concise.

        Instructions importantes:
        - Utilise les documents pertinents fournis dans le contexte pour donner des réponses précises et informées
        - Si les documents contiennent des informations utiles, cite-les dans ta réponse
        - Si tu ne trouves pas d'informations pertinentes dans les documents, dis-le clairement
        - Reste factuel et basé sur les informations fournies
        - Si tu ne connais pas la réponse, dis-le honnêtement
        PROMPT;

    private const MAX_TOOL_ITERATIONS = 3; // guards against a runaway tool-call loop

    public function __construct(
        private readonly ProviderSelectionService $providerSelectionService,
        private readonly RagContextService $ragContextService,
        private readonly WorkflowExecutionService $workflowExecutionService,
    ) {
    }

    /**
     * @param ChatMessage[] $history
     */
    public function generateReply(
        string $userMessage,
        array $history,
        ?AiAgent $agent = null,
        ?Conversation $conversation = null,
    ): ChatReplyResult {
        $llmClient = $this->providerSelectionService->getLlmClient(AiProviderUsage::Chat);
        $ragResults = $this->ragContextService->buildContext($userMessage, $agent);
        $messages = $this->buildMessages($agent, $history, $userMessage, $ragResults);
        $toolSpecs = $this->buildToolSpecs($agent);

        $toolTrace = [];

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; ++$i) {
            $result = $llmClient->complete($messages, $toolSpecs ?: null);
            $messages[] = $result->message;

            if (!$result->message->toolCalls) {
                return new ChatReplyResult($result->message->content, $result->usage, $toolTrace);
            }

            foreach ($result->message->toolCalls as $call) {
                $workflow = $this->resolveWorkflow($agent, $call->name);
                if (!$workflow) {
                    $output = ['error' => "Unknown tool '{$call->name}'"];
                    $execStatus = 'failed';
                } else {
                    $execution = $this->workflowExecutionService->execute($workflow->getId(), $call->arguments, $conversation);
                    $output = 'completed' === $execution->getStatus()->value
                        ? $execution->getOutputData()
                        : ['error' => $execution->getErrorMessage()];
                    $execStatus = $execution->getStatus()->value;
                }

                $toolTrace[] = [
                    'tool' => $call->name,
                    'arguments' => $call->arguments,
                    'status' => $execStatus,
                    'output' => $output,
                ];
                $messages[] = new ChatMessage(
                    role: 'tool',
                    content: json_encode($output, \JSON_PARTIAL_OUTPUT_ON_ERROR),
                    toolCallId: $call->id,
                    name: $call->name,
                );
            }
        }

        // Iteration budget exhausted -- force a final answer without further tool access.
        $final = $llmClient->complete($messages);

        return new ChatReplyResult($final->message->content, $final->usage, $toolTrace);
    }

    /**
     * @return ToolSpec[]
     */
    private function buildToolSpecs(?AiAgent $agent): array
    {
        if (!$agent) {
            return [];
        }

        return array_values(array_map(
            static fn (Workflow $wf) => new ToolSpec(
                name: self::toolName($wf->getName()),
                description: $wf->getDescription() ?: $wf->getName(),
                parameters: $wf->getParametersSchema() ?: ['type' => 'object', 'properties' => []],
            ),
            $agent->getActiveWorkflows()->toArray(),
        ));
    }

    private function resolveWorkflow(?AiAgent $agent, string $toolName): ?Workflow
    {
        if (!$agent) {
            return null;
        }
        foreach ($agent->getActiveWorkflows() as $wf) {
            if (self::toolName($wf->getName()) === $toolName) {
                return $wf;
            }
        }

        return null;
    }

    /**
     * @param ChatMessage[]                    $history
     * @param array<int, array<string, mixed>> $ragResults
     *
     * @return ChatMessage[]
     */
    private function buildMessages(?AiAgent $agent, array $history, string $userMessage, array $ragResults): array
    {
        $systemPrompt = $agent && '' !== $agent->getSystemPrompt() ? $agent->getSystemPrompt() : self::DEFAULT_SYSTEM_PROMPT;

        if ($ragResults) {
            $docsText = "Documents pertinents trouvés dans la base de connaissances:\n".implode("\n", array_map(
                static fn (int $i, array $doc) => ($i + 1).'. '.($doc['content'] ?? ''),
                array_keys($ragResults),
                $ragResults,
            ));
            $systemPrompt = "{$systemPrompt}\n\n{$docsText}";
        }

        $messages = [new ChatMessage(role: 'system', content: $systemPrompt)];
        array_push($messages, ...array_slice($history, -6));
        $messages[] = new ChatMessage(role: 'user', content: $userMessage);

        return $messages;
    }

    /**
     * LLM tool names must be simple identifiers; Workflow.name is free text.
     */
    private static function toolName(string $workflowName): string
    {
        return mb_substr(preg_replace('/[^a-zA-Z0-9_-]/', '_', $workflowName), 0, 64);
    }
}
