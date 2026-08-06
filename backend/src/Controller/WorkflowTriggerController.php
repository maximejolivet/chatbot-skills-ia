<?php

namespace App\Controller;

use App\Entity\Workflow;
use App\Enum\WorkflowStatus;
use App\Workflow\WorkflowExecutionSerializer;
use App\Workflow\WorkflowExecutionService;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * NOTE: this backend has no message queue yet (see WorkflowExecutionService),
 * so triggering runs synchronously and returns the completed execution
 * instead of queuing it -- same simplification as /test/.
 */
#[AsController]
final class WorkflowTriggerController
{
    public function __construct(private readonly WorkflowExecutionService $workflowExecutionService)
    {
    }

    public function __invoke(Workflow $data, Request $request): JsonResponse
    {
        if (WorkflowStatus::Active !== $data->getStatus()) {
            return new JsonResponse(['error' => 'Workflow is not active'], 400);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $inputData = is_array($body['input_data'] ?? null) ? $body['input_data'] : [];

        $execution = $this->workflowExecutionService->execute($data->getId(), $inputData);

        return new JsonResponse(WorkflowExecutionSerializer::serialize($execution), 201);
    }
}
