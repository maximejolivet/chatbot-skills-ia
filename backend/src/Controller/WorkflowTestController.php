<?php

namespace App\Controller;

use App\Entity\Workflow;
use App\Message\ExecuteWorkflowMessage;
use App\Workflow\WorkflowExecutionSerializer;
use App\Workflow\WorkflowExecutionService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Same async pattern as /trigger (see WorkflowTriggerController) -- unlike
 * /trigger, there's no is-active check here either.
 */
#[AsController]
final readonly class WorkflowTestController
{
    public function __construct(
        private WorkflowExecutionService $workflowExecutionService,
        private MessageBusInterface $messageBus,
    ) {}

    // See WorkflowStepsController's comment -- Workflow's resource-level
    // security doesn't apply to custom-controller operations.
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Workflow $data, Request $request): JsonResponse
    {
        $workflowId = $data->getId() ?? throw new \LogicException('Workflow must be persisted.');

        $body = json_decode($request->getContent(), true) ?? [];
        $inputData = is_array($body) && is_array($body['input_data'] ?? null) ? $body['input_data'] : [];

        $execution = $this->workflowExecutionService->createPendingExecution($workflowId, $inputData);
        $executionId = $execution->getId() ?? throw new \LogicException('WorkflowExecution must be persisted.');
        $this->messageBus->dispatch(new ExecuteWorkflowMessage($executionId));

        return new JsonResponse(WorkflowExecutionSerializer::serialize($execution), 202);
    }
}
