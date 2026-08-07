<?php

namespace App\Controller;

use App\Entity\Workflow;
use App\Enum\WorkflowStatus;
use App\Message\ExecuteWorkflowMessage;
use App\Workflow\WorkflowExecutionSerializer;
use App\Workflow\WorkflowExecutionService;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Creates the WorkflowExecution row (status 'pending') and dispatches it to
 * the `async` transport (see ExecuteWorkflowMessageHandler) -- returns as
 * soon as it's queued, not once it's done; poll
 * GET /api/workflow_executions/{id} for the final status.
 */
#[AsController]
final class WorkflowTriggerController
{
    public function __construct(
        private readonly WorkflowExecutionService $workflowExecutionService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(Workflow $data, Request $request): JsonResponse
    {
        if (WorkflowStatus::Active !== $data->getStatus()) {
            return new JsonResponse(['error' => 'Workflow is not active'], 400);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $inputData = is_array($body['input_data'] ?? null) ? $body['input_data'] : [];

        $execution = $this->workflowExecutionService->createPendingExecution($data->getId(), $inputData);
        $this->messageBus->dispatch(new ExecuteWorkflowMessage($execution->getId()));

        return new JsonResponse(WorkflowExecutionSerializer::serialize($execution), 202);
    }
}
