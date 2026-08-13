<?php

namespace App\Tests\Workflow;

use App\Entity\Conversation;
use App\Entity\WorkflowStep;
use App\Enum\WorkflowStepType;
use App\Repository\WorkflowRepository;
use App\Repository\WorkflowStepRepository;
use App\Workflow\WorkflowExecutionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * handleSetConversation() and resolveEnvHeaders() are private -- invoked via
 * reflection rather than only through execute(), which would otherwise drag
 * in a full WorkflowExecution/WorkflowStepRepository fixture unrelated to
 * what these two methods actually do.
 */
final class WorkflowExecutionServiceTest extends TestCase
{
    private function service(?EntityManagerInterface $entityManager = null): WorkflowExecutionService
    {
        return new WorkflowExecutionService(
            $this->createStub(WorkflowRepository::class),
            $this->createStub(WorkflowStepRepository::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(MailerInterface::class),
            'from@test.local',
            $this->createStub(HttpClientInterface::class),
        );
    }

    private function invokePrivate(WorkflowExecutionService $service, string $method, array $args): mixed
    {
        return (new \ReflectionMethod($service, $method))->invoke($service, ...$args);
    }

    public function testResolveEnvHeadersResolvesEnvPlaceholder(): void
    {
        $_ENV['TEST_WORKFLOW_API_KEY'] = 'secret-123';

        $result = $this->invokePrivate($this->service(), 'resolveEnvHeaders', [
            ['Authorization' => '%env(TEST_WORKFLOW_API_KEY)%'],
        ]);

        self::assertSame(['Authorization' => 'secret-123'], $result);

        unset($_ENV['TEST_WORKFLOW_API_KEY']);
    }

    public function testResolveEnvHeadersLeavesPlainStringsUntouched(): void
    {
        $result = $this->invokePrivate($this->service(), 'resolveEnvHeaders', [
            ['Content-Type' => 'application/json'],
        ]);

        self::assertSame(['Content-Type' => 'application/json'], $result);
    }

    public function testResolveEnvHeadersFallsBackToEmptyStringForMissingEnvVar(): void
    {
        $result = $this->invokePrivate($this->service(), 'resolveEnvHeaders', [
            ['X-Key' => '%env(DOES_NOT_EXIST_IN_ANY_ENV)%'],
        ]);

        self::assertSame(['X-Key' => ''], $result);
    }

    public function testHandleSetConversationSkipsWhenNoConversation(): void
    {
        $step = (new WorkflowStep())
            ->setStepType(WorkflowStepType::SetConversation)
            ->setConfiguration(['fields' => ['visitor_first_name' => 'first_name']]);

        $result = $this->invokePrivate($this->service(), 'handleSetConversation', [$step, ['first_name' => 'Kilian'], null]);

        self::assertSame('skipped', $result['status']);
        self::assertSame('no conversation in context', $result['reason']);
    }

    public function testHandleSetConversationWritesOnlyWhitelistedMappedFields(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $conversation = new Conversation();
        $step = (new WorkflowStep())
            ->setStepType(WorkflowStepType::SetConversation)
            ->setConfiguration(['fields' => [
                'visitor_first_name' => 'first_name',
                'visitor_last_name' => 'last_name',
                'not_a_real_field' => 'whatever',
            ]]);

        $result = $this->invokePrivate($this->service($entityManager), 'handleSetConversation', [
            $step,
            ['first_name' => 'Kilian', 'whatever' => 'ignored'],
            $conversation,
        ]);

        self::assertSame('updated', $result['status']);
        self::assertSame(['visitor_first_name' => 'Kilian'], $result['fields']);
        self::assertSame('Kilian', $conversation->getVisitorFirstName());
        self::assertNull($conversation->getVisitorLastName());
    }

    public function testHandleSetConversationSkipsAndDoesNotFlushWhenNothingMatches(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $conversation = new Conversation();
        $step = (new WorkflowStep())
            ->setStepType(WorkflowStepType::SetConversation)
            ->setConfiguration(['fields' => ['visitor_first_name' => 'first_name']]);

        $result = $this->invokePrivate($this->service($entityManager), 'handleSetConversation', [
            $step,
            ['some_other_key' => 'value'],
            $conversation,
        ]);

        self::assertSame('skipped', $result['status']);
        self::assertSame([], $result['fields']);
    }
}
