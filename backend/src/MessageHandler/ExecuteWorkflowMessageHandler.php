<?php

namespace App\MessageHandler;

use App\Entity\WorkflowExecution;
use App\Message\ExecuteWorkflowMessage;
use App\Workflow\WorkflowExecutionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ExecuteWorkflowMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkflowExecutionService $workflowExecutionService,
        private readonly LoggerInterface $logger,
    ) {
    }

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
