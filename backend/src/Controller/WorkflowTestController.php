<?php

namespace App\Controller;

use App\Entity\Workflow;
use App\Workflow\WorkflowExecutionSerializer;
use App\Workflow\WorkflowExecutionService;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sync: runs the same engine as /trigger, blocking for the result. Unlike
 * /trigger, there's no is-active check here either.
 */
#[AsController]
final class WorkflowTestController
{
    public function __construct(private readonly WorkflowExecutionService $workflowExecutionService)
    {
    }

    public function __invoke(Workflow $data, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $inputData = is_array($body['input_data'] ?? null) ? $body['input_data'] : [];

        $execution = $this->workflowExecutionService->execute($data->getId(), $inputData);

        return new JsonResponse(WorkflowExecutionSerializer::serialize($execution));
    }
}
