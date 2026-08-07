<?php

namespace App\Controller;

use App\Entity\Workflow;
use App\Message\ExecuteWorkflowMessage;
use App\Workflow\WorkflowExecutionSerializer;
use App\Workflow\WorkflowExecutionService;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Same async pattern as /trigger (see WorkflowTriggerController) -- unlike
 * /trigger, there's no is-active check here either.
 */
#[AsController]
final class WorkflowTestController
{
    public function __construct(
        private readonly WorkflowExecutionService $workflowExecutionService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(Workflow $data, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $inputData = is_array($body['input_data'] ?? null) ? $body['input_data'] : [];

        $execution = $this->workflowExecutionService->createPendingExecution($data->getId(), $inputData);
        $this->messageBus->dispatch(new ExecuteWorkflowMessage($execution->getId()));

        return new JsonResponse(WorkflowExecutionSerializer::serialize($execution), 202);
    }
}
