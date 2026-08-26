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
        return new \ReflectionMethod($service, $method)->invoke($service, ...$args);
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
        $step = new WorkflowStep()
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
        $step = new WorkflowStep()
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
        $step = new WorkflowStep()
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

    public function testHandleConditionRunsTrueActionSetField(): void
    {
        $step = new WorkflowStep()
            ->setStepType(WorkflowStepType::Condition)
            ->setConfiguration([
                'condition' => ['field' => 'score', 'operator' => 'greater_than', 'value' => 5],
                'true_action' => ['type' => 'set_field', 'field' => 'tier', 'value' => 'gold'],
                'false_action' => ['type' => 'set_field', 'field' => 'tier', 'value' => 'standard'],
            ]);

        $result = $this->invokePrivate($this->service(), 'handleCondition', [$step, ['score' => 10]]);

        self::assertSame('gold', $result['tier']);
    }

    public function testHandleConditionRunsFalseActionAddField(): void
    {
        $step = new WorkflowStep()
            ->setStepType(WorkflowStepType::Condition)
            ->setConfiguration([
                'condition' => ['field' => 'score', 'operator' => 'greater_than', 'value' => 5],
                'true_action' => ['type' => 'set_field', 'field' => 'bonus', 'value' => 100],
                'false_action' => ['type' => 'add_field', 'field' => 'bonus', 'value' => 1],
            ]);

        $result = $this->invokePrivate($this->service(), 'handleCondition', [$step, ['score' => 1, 'bonus' => 4]]);

        self::assertSame(5, $result['bonus']);
    }

    public function testHandleConditionRemoveFieldAction(): void
    {
        $step = new WorkflowStep()
            ->setStepType(WorkflowStepType::Condition)
            ->setConfiguration([
                'condition' => ['field' => 'flagged', 'operator' => 'equals', 'value' => true],
                'true_action' => ['type' => 'remove_field', 'field' => 'draft'],
            ]);

        $result = $this->invokePrivate($this->service(), 'handleCondition', [$step, ['flagged' => true, 'draft' => 'wip']]);

        self::assertIsArray($result);
        self::assertArrayNotHasKey('draft', $result);
    }

    public function testHandleConditionUnknownActionTypeIsNoOp(): void
    {
        $step = new WorkflowStep()
            ->setStepType(WorkflowStepType::Condition)
            ->setConfiguration([
                'condition' => ['field' => 'x', 'operator' => 'equals', 'value' => 1],
                'true_action' => ['type' => 'send_carrier_pigeon', 'field' => 'x', 'value' => 2],
            ]);

        $result = $this->invokePrivate($this->service(), 'handleCondition', [$step, ['x' => 1]]);

        self::assertSame(['x' => 1], $result);
    }

    public function testHandleDataTransformSetAddAndRemove(): void
    {
        $step = new WorkflowStep()
            ->setStepType(WorkflowStepType::DataTransform)
            ->setConfiguration(['transformations' => [
                ['field' => 'greeting', 'operation' => 'set', 'value' => 'Hello {{name}}'],
                ['field' => 'count', 'operation' => 'add', 'value' => 1],
                ['field' => 'secret', 'operation' => 'remove'],
            ]]);

        $result = $this->invokePrivate($this->service(), 'handleDataTransform', [
            $step,
            ['name' => 'Kilian', 'count' => 4, 'secret' => 'shh'],
        ]);

        self::assertIsArray($result);
        self::assertSame('Hello Kilian', $result['greeting']);
        self::assertSame(5, $result['count']);
        self::assertArrayNotHasKey('secret', $result);
    }
}
