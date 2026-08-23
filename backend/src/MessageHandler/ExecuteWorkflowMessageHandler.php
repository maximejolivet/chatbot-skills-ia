<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\WorkflowExecution;
use App\Message\ExecuteWorkflowMessage;
use App\Workflow\WorkflowExecutionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ExecuteWorkflowMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkflowExecutionService $workflowExecutionService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ExecuteWorkflowMessage $message): void
    {
        $execution = $this->entityManager->find(WorkflowExecution::class, $message->workflowExecutionId);
        if (!$execution instanceof WorkflowExecution) {
            $this->logger->warning('ExecuteWorkflowMessage for missing execution {id}, skipping.', ['id' => $message->workflowExecutionId]);

            return;
        }

        // run() itself already catches step failures and records them on
        // the execution (status 'failed' + errorMessage) -- nothing to
        // catch here.
        $this->workflowExecutionService->run($execution);
    }
}
