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

    private HttpClientInterface $httpClient;

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

        $execution = (new WorkflowExecution())
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

        try {
            foreach ($this->workflowStepRepository->findActiveOrdered($execution->getWorkflow()->getId()) as $step) {
                $stepResult = $this->executeStep($step, $currentData, $execution->getConversation());
                $results[] = $stepResult;
                if ('failed' === $stepResult['status']) {
                    $status = WorkflowExecutionStatus::Failed;
                    $errorMessage = $stepResult['error_message'] ?? '';
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
        $method = mb_strtoupper($config['method'] ?? 'GET');
        $data = $this->replacePlaceholders($config['data'] ?? [], $inputData);
        $url = $this->replacePlaceholders($url, $inputData);

        $options = ['headers' => $this->resolveEnvHeaders($config['headers'] ?? [])];
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
            static fn ($value) => is_string($value) && preg_match('/^%env\((\w+)\)%$/', $value, $m)
                ? (string) ($_ENV[$m[1]] ?? getenv($m[1]) ?: '')
                : $value,
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
        $subject = $this->replacePlaceholders($config['subject'] ?? '', $inputData);
        $body = $this->replacePlaceholders($config['body'] ?? '', $inputData);

        $email = (new Email())
            ->from($this->mailerFromAddress)
            ->to($toEmail)
            ->subject($subject)
            ->text($body);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException("Failed to send email to {$toEmail}: {$e->getMessage()}", previous: $e);
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

        $webhookUrl = $this->replacePlaceholders($webhookUrl, $inputData);
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
        foreach ($step->getConfiguration()['transformations'] ?? [] as $transformation) {
            $field = $transformation['field'] ?? null;
            $operation = $transformation['operation'] ?? null;
            $value = $transformation['value'] ?? null;
            if (!$field || !$operation) {
                continue;
            }
            if ('set' === $operation) {
                $resultData[$field] = $this->replacePlaceholders($value, $inputData);
            } elseif ('remove' === $operation) {
                unset($resultData[$field]);
            } elseif ('add' === $operation) {
                $addition = $this->replacePlaceholders($value, $inputData);
                $resultData[$field] = array_key_exists($field, $resultData)
                    ? $resultData[$field] + $addition
                    : $addition;
            }
        }

        return $resultData;
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

        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;
        if (!$field || !$operator) {
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

        return $action ? $this->executeAction($action, $inputData) : $inputData;
    }

    /**
     * @return array{delay_seconds: int, status: string}
     */
    private function handleDelay(WorkflowStep $step, array $inputData): array
    {
        $delaySeconds = (int) ($step->getConfiguration()['delay_seconds'] ?? 0);
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
        $url = $this->replacePlaceholders($url, $inputData);
        $method = mb_strtoupper($config['method'] ?? 'POST');

        $response = $this->httpClient->request($method, $url, [
            'headers' => $this->resolveEnvHeaders($config['headers'] ?? []),
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

        $updated = [];
        foreach ($step->getConfiguration()['fields'] ?? [] as $conversationField => $inputKey) {
            if (!isset($setters[$conversationField]) || !isset($inputData[$inputKey])) {
                continue;
            }
            $setters[$conversationField]((string) $inputData[$inputKey]);
            $updated[$conversationField] = $inputData[$inputKey];
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
            return array_map(fn ($v) => $this->replacePlaceholders($v, $data), $value);
        }
        if (is_string($value)) {
            return preg_replace_callback(
                '/\{\{(\w+)\}\}/',
                static fn (array $m) => isset($data[$m[1]]) ? (string) $data[$m[1]] : $m[0],
                $value,
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $inputData
     *
     * @return array<string, mixed>
     */
    private function executeAction(array $action, array $inputData): array
    {
        if ('set_field' === ($action['type'] ?? null)) {
            $field = $action['field'] ?? null;
            $value = $action['value'] ?? null;
            if ($field && null !== $value) {
                $inputData[$field] = $this->replacePlaceholders($value, $inputData);
            }
        }

        return $inputData;
    }
}
