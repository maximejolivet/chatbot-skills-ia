<?php

namespace App\Workflow;

use App\Entity\Conversation;
use App\Entity\Workflow;
use App\Entity\WorkflowExecution;
use App\Entity\WorkflowStep;
use App\Enum\WorkflowExecutionStatus;
use App\Enum\WorkflowStepType;
use App\Repository\WorkflowRepository;
use App\Repository\WorkflowStepRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The one real step-execution engine.
 *
 * Two ways to run a workflow:
 * - execute(): synchronous, creates the row and runs it inline, returning
 *   the completed WorkflowExecution. Used only by
 *   App\Chat\ChatOrchestrationService's tool-calling loop, which needs the
 *   result immediately to continue the conversation with the LLM -- this
 *   call site deliberately stays synchronous, not dispatched to Messenger.
 * - createPendingExecution() + the `async` transport (see
 *   App\Message\ExecuteWorkflowMessage /
 *   App\MessageHandler\ExecuteWorkflowMessageHandler): used by
 *   WorkflowTriggerController/WorkflowTestController, which return as soon
 *   as the row exists and let the caller poll GET /api/workflow_executions/{id}.
 */
final class WorkflowExecutionService
{
    /**
     * @var array<string, callable(WorkflowStep, array<string, mixed>, ?Conversation): array<string, mixed>>
     */
    private array $stepHandlers;

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        private readonly WorkflowRepository $workflowRepository,
        private readonly WorkflowStepRepository $workflowStepRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $mailerFromAddress,
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
        $this->stepHandlers = [
            WorkflowStepType::ApiCall->value => $this->handleApiCall(...),
            WorkflowStepType::Email->value => $this->handleEmail(...),
            WorkflowStepType::Notification->value => $this->handleNotification(...),
            WorkflowStepType::DataTransform->value => $this->handleDataTransform(...),
            WorkflowStepType::Condition->value => $this->handleCondition(...),
            WorkflowStepType::Delay->value => $this->handleDelay(...),
            WorkflowStepType::Webhook->value => $this->handleWebhook(...),
            WorkflowStepType::SetConversation->value => $this->handleSetConversation(...),
        ];
    }

    /**
     * @param array<string, mixed> $inputData
     */
    public function execute(int $workflowId, array $inputData, ?Conversation $conversation = null): WorkflowExecution
    {
        return $this->run($this->createPendingExecution($workflowId, $inputData, $conversation));
    }

    /**
     * Persists a WorkflowExecution row (status 'pending') without running it
     * -- the caller either runs it inline (execute()) or dispatches
     * ExecuteWorkflowMessage for a worker to pick up.
     *
     * @param array<string, mixed> $inputData
     */
    public function createPendingExecution(int $workflowId, array $inputData, ?Conversation $conversation = null): WorkflowExecution
    {
        $workflow = $this->workflowRepository->getActive($workflowId);
        if (!$workflow) {
            throw new \RuntimeException("Workflow {$workflowId} not found or inactive.");
        }

        $execution = new WorkflowExecution()
            ->setWorkflow($workflow)
            ->setInputData($inputData)
            ->setConversation($conversation);
        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        return $execution;
    }

    public function run(WorkflowExecution $execution): WorkflowExecution
    {
        $execution->setStatus(WorkflowExecutionStatus::Running)->setStartedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $currentData = $execution->getInputData();
        $results = [];
        $status = WorkflowExecutionStatus::Completed;
        $errorMessage = '';

        $workflowId = $execution->getWorkflow()->getId() ?? throw new \LogicException('Workflow must be persisted.');

        try {
            foreach ($this->workflowStepRepository->findActiveOrdered($workflowId) as $step) {
                $stepResult = $this->executeStep($step, $currentData, $execution->getConversation());
                $results[] = $stepResult;
                if ('failed' === $stepResult['status']) {
                    $status = WorkflowExecutionStatus::Failed;
                    $errorMessage = \is_string($stepResult['error_message'] ?? null) ? $stepResult['error_message'] : '';
                    break;
                }
                if (isset($stepResult['output_data']) && is_array($stepResult['output_data'])) {
                    $currentData = [...$currentData, ...$stepResult['output_data']];
                }
            }
        } catch (\Throwable $e) {
            $status = WorkflowExecutionStatus::Failed;
            $errorMessage = $e->getMessage();
        }

        $execution
            ->setStatus($status)
            ->setOutputData($currentData)
            ->setExecutionLog($results)
            ->setErrorMessage($errorMessage)
            ->setCompletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $execution;
    }

    /**
     * @param array<string, mixed> $inputData
     *
     * @return array<string, mixed>
     */
    private function executeStep(WorkflowStep $step, array $inputData, ?Conversation $conversation): array
    {
        $start = microtime(true);
        try {
            $handler = $this->stepHandlers[$step->getStepType()->value] ?? null;
            if (!$handler) {
                throw new \RuntimeException("Unknown step type: {$step->getStepType()->value}");
            }
            // Every handler except handleSetConversation() ignores this 3rd
            // argument -- fine, PHP doesn't enforce arity on excess args.
            $result = $handler($step, $inputData, $conversation);

            return [
                'step_id' => $step->getId(),
                'step_name' => $step->getName(),
                'step_type' => $step->getStepType()->value,
                'status' => 'success',
                'output_data' => $result,
                'execution_time' => microtime(true) - $start,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Error executing step {id}: {error}', ['id' => $step->getId(), 'error' => $e->getMessage()]);

            return [
                'step_id' => $step->getId(),
                'step_name' => $step->getName(),
                'step_type' => $step->getStepType()->value,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'execution_time' => microtime(true) - $start,
            ];
        }
    }

    /**
     * @param array<string, mixed> $inputData
     *
     * @return array<string, mixed>
     */
    private function handleApiCall(WorkflowStep $step, array $inputData): array
    {
        $config = $step->getConfiguration();
        $url = $config['url'] ?? null;
        if (!$url) {
            throw new \RuntimeException('API URL not configured');
        }
        $method = mb_strtoupper(self::expectConfigString($config['method'] ?? 'GET', 'api_call.method'));
        $data = $this->replacePlaceholders($config['data'] ?? [], $inputData);
        $url = self::expectConfigString($this->replacePlaceholders($url, $inputData), 'api_call.url');

        $options = ['headers' => $this->resolveEnvHeaders(self::expectConfigStringMap($config['headers'] ?? []))];
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $options['json'] = $data;
        }

        $response = $this->httpClient->request($method, $url, [...$options, 'timeout' => 30]);
        $content = $response->getContent(); // throws on 4xx/5xx, matching raise_for_status()

        return [
            'status_code' => $response->getStatusCode(),
            'response_data' => '' !== $content ? $response->toArray(false) : null,
        ];
    }

    /**
     * Resolves `%env(VAR_NAME)%` header values against the real environment
     * (never against $inputData) -- lets a step's stored `configuration`
     * reference a secret (e.g. a third-party API key) by name instead of
     * embedding it in plaintext in the workflow_step row / admin API.
     *
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private function resolveEnvHeaders(array $headers): array
    {
        return array_map(
            static function (string $value): string {
                if (1 !== preg_match('/^%env\((\w+)\)%$/', $value, $m)) {
                    return $value;
                }
                $envValue = $_ENV[$m[1]] ?? getenv($m[1]);

                return \is_string($envValue) ? $envValue : '';
            },
            $headers,
        );
    }

    /**
     * @param array<string, mixed> $inputData
     *
     * @return array<string, mixed>
     */
    private function handleEmail(WorkflowStep $step, array $inputData): array
    {
        $config = $step->getConfiguration();
        $toEmail = $config['to_email'] ?? null;
        if (!$toEmail) {
            throw new \RuntimeException('Email recipient not configured');
        }
        $toEmail = self::expectConfigString($toEmail, 'email.to_email');
        $subject = self::expectConfigString($this->replacePlaceholders($config['subject'] ?? '', $inputData), 'email.subject');
        $body = self::expectConfigString($this->replacePlaceholders($config['body'] ?? '', $inputData), 'email.body');

        $email = new Email()
            ->from($this->mailerFromAddress)
            ->to($toEmail)
            ->subject($subject)
            ->text($body);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException("Failed to send email to {$toEmail}: {$e->getMessage()}", $e->getCode(), previous: $e);
        }

        return ['to_email' => $toEmail, 'subject' => $subject, 'body' => $body, 'status' => 'sent'];
    }

    /**
     * Posts to a Slack/Discord/Mattermost-style incoming webhook (`{"text": ...}`)
     * when `webhook_url` is configured; otherwise just logs, same as before --
     * "notification" has no single real channel of its own (that's what the
     * `webhook` step type is for), so this only becomes real once a caller
     * opts in with a URL.
     *
     * @param array<string, mixed> $inputData
     *
     * @return array<string, mixed>
     */
    private function handleNotification(WorkflowStep $step, array $inputData): array
    {
        $config = $step->getConfiguration();
        $message = $this->replacePlaceholders($config['message'] ?? '', $inputData);
        $channel = $config['channel'] ?? 'general';
        $webhookUrl = $config['webhook_url'] ?? null;

        if (!$webhookUrl) {
            $this->logger->info('Notification to {channel}: {message}', ['channel' => $channel, 'message' => $message]);

            return ['channel' => $channel, 'message' => $message, 'status' => 'logged'];
        }

        $webhookUrl = self::expectConfigString($this->replacePlaceholders($webhookUrl, $inputData), 'notification.webhook_url');
        $this->httpClient->request('POST', $webhookUrl, [
            'json' => ['text' => $message, 'channel' => $channel],
            'timeout' => 30,
        ])->getContent(); // throws on 4xx/5xx

        return ['channel' => $channel, 'message' => $message, 'status' => 'sent'];
    }

    /**
     * @param array<string, mixed> $inputData
     *
     * @return array<string, mixed>
     */
    private function handleDataTransform(WorkflowStep $step, array $inputData): array
    {
        $resultData = $inputData;
        $transformations = $step->getConfiguration()['transformations'] ?? [];
        foreach (\is_array($transformations) ? $transformations : [] as $transformation) {
            if (!\is_array($transformation)) {
                continue;
            }
            $operation = $transformation['operation'] ?? null;
            if (!\is_string($operation) || '' === $operation) {
                continue;
            }
            $field = $transformation['field'] ?? null;
            $resultData = $this->applyFieldOperation($resultData, $operation, \is_string($field) ? $field : null, $transformation['value'] ?? null);
        }

        return $resultData;
    }

    /**
     * `set`/`remove`/`add` on a single field of `$data`. Shared by
     * handleDataTransform() (a `transformations` list) and executeAction()
     * (a condition step's single true_action/false_action) so a condition
     * branch has the same field-mutation vocabulary as a dedicated
     * data_transform step instead of a smaller, one-off copy of it.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function applyFieldOperation(array $data, string $operation, ?string $field, mixed $value): array
    {
        if (!$field) {
            return $data;
        }

        return match ($operation) {
            'set' => [...$data, $field => $this->replacePlaceholders($value, $data)],
            'remove' => array_diff_key($data, [$field => true]),
            'add' => [...$data, $field => array_key_exists($field, $data)
                ? self::numericValue($data[$field]) + self::numericValue($this->replacePlaceholders($value, $data))
                : $this->replacePlaceholders($value, $data)],
            default => $data,
        };
    }

    /**
     * @param array<string, mixed> $inputData
     *
     * @return array<string, mixed>
     */
    private function handleCondition(WorkflowStep $step, array $inputData): array
    {
        $config = $step->getConfiguration();
        $condition = $config['condition'] ?? null;
        if (!$condition) {
            return $inputData;
        }

        $field = \is_array($condition) ? ($condition['field'] ?? null) : null;
        $operator = \is_array($condition) ? ($condition['operator'] ?? null) : null;
        $value = \is_array($condition) ? ($condition['value'] ?? null) : null;
        if (!\is_string($field) || !\is_string($operator)) {
            return $inputData;
        }

        $fieldValue = $inputData[$field] ?? null;
        $conditionMet = match ($operator) {
            'equals' => $fieldValue == $value,
            'not_equals' => $fieldValue != $value,
            'contains' => str_contains((string) $fieldValue, (string) $value),
            'greater_than' => (float) $fieldValue > (float) $value,
            'less_than' => (float) $fieldValue < (float) $value,
            default => false,
        };

        $action = $config[$conditionMet ? 'true_action' : 'false_action'] ?? null;

        return $this->executeAction(self::expectConfigArray($action), $inputData);
    }

    /**
     * @param array<string, mixed> $inputData
     *
     * @return array{delay_seconds: int, status: string}
     */
    private function handleDelay(WorkflowStep $step, array $inputData): array
    {
        $rawDelay = $step->getConfiguration()['delay_seconds'] ?? 0;
        $delaySeconds = \is_numeric($rawDelay) ? (int) $rawDelay : 0;
        if ($delaySeconds > 0) {
            sleep($delaySeconds);
        }

        return ['delay_seconds' => $delaySeconds, 'status' => 'completed'];
    }

    /**
     * @param array<string, mixed> $inputData
     *
     * @return array<string, mixed>
     */
    private function handleWebhook(WorkflowStep $step, array $inputData): array
    {
        $config = $step->getConfiguration();
        $url = $config['url'] ?? null;
        if (!$url) {
            throw new \RuntimeException('Webhook URL not configured');
        }
        $url = self::expectConfigString($this->replacePlaceholders($url, $inputData), 'webhook.url');
        $method = mb_strtoupper(self::expectConfigString($config['method'] ?? 'POST', 'webhook.method'));

        $response = $this->httpClient->request($method, $url, [
            'headers' => $this->resolveEnvHeaders(self::expectConfigStringMap($config['headers'] ?? [])),
            'json' => $inputData,
            'timeout' => 30,
        ]);
        $content = $response->getContent();

        return [
            'status_code' => $response->getStatusCode(),
            'response_data' => '' !== $content ? $response->toArray(false) : null,
        ];
    }

    /**
     * Writes whitelisted fields onto the execution's Conversation -- lets the
     * agent capture free-form facts about the visitor (e.g. their name) via
     * tool arguments and persist them structurally, which no other step type
     * can do (they only ever touch $inputData / external services).
     *
     * `configuration.fields` maps a Conversation field name to the
     * $inputData key holding its value, e.g.
     * {"visitor_first_name": "first_name", "visitor_last_name": "last_name"}.
     *
     * @param array<string, mixed> $inputData
     *
     * @return array<string, mixed>
     */
    private function handleSetConversation(WorkflowStep $step, array $inputData, ?Conversation $conversation): array
    {
        if (!$conversation) {
            return ['status' => 'skipped', 'reason' => 'no conversation in context'];
        }

        $setters = [
            'visitor_first_name' => $conversation->setVisitorFirstName(...),
            'visitor_last_name' => $conversation->setVisitorLastName(...),
        ];

        $fields = $step->getConfiguration()['fields'] ?? [];
        $updated = [];
        foreach (\is_array($fields) ? $fields : [] as $conversationField => $inputKey) {
            if (!\is_string($conversationField) || !\is_string($inputKey) || !isset($setters[$conversationField])) {
                continue;
            }
            $value = $inputData[$inputKey] ?? null;
            if (!\is_scalar($value)) {
                continue;
            }
            $setters[$conversationField]((string) $value);
            $updated[$conversationField] = $value;
        }

        if ($updated) {
            $this->entityManager->flush();
        }

        return ['status' => $updated ? 'updated' : 'skipped', 'fields' => $updated];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function replacePlaceholders(mixed $value, array $data): mixed
    {
        if (is_array($value)) {
            return array_map(fn($v): mixed => $this->replacePlaceholders($v, $data), $value);
        }
        if (is_string($value)) {
            return preg_replace_callback(
                '/\{\{(\w+)\}\}/',
                static fn(array $m): string => isset($data[$m[1]]) ? (string) $data[$m[1]] : $m[0],
                $value,
            );
        }

        return $value;
    }

    /**
     * `condition`'s true_action/false_action: `{"type": "set_field"|"add_field"|"remove_field", "field": "...", "value"?: ...}`.
     * Same three operations as data_transform's `transformations` (see
     * applyFieldOperation()) -- an unrecognized/missing `type` is a no-op,
     * same as an unrecognized data_transform `operation`.
     *
     * @param array<string, mixed> $action
     * @param array<string, mixed> $inputData
     *
     * @return array<string, mixed>
     */
    private function executeAction(array $action, array $inputData): array
    {
        $operation = match ($action['type'] ?? null) {
            'set_field' => 'set',
            'add_field' => 'add',
            'remove_field' => 'remove',
            default => null,
        };

        $field = $action['field'] ?? null;

        return $operation
            ? $this->applyFieldOperation($inputData, $operation, \is_string($field) ? $field : null, $action['value'] ?? null)
            : $inputData;
    }

    private static function numericValue(mixed $value): int|float
    {
        return \is_numeric($value) ? $value + 0 : 0;
    }

    private static function expectConfigString(mixed $value, string $context): string
    {
        if (!\is_string($value)) {
            throw new \RuntimeException("Workflow step configuration \"{$context}\" must be a string.");
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private static function expectConfigStringMap(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (\is_string($key) && \is_string($item)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private static function expectConfigArray(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
