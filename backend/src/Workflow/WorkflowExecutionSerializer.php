<?php

namespace App\Workflow;

use App\Entity\WorkflowExecution;

final class WorkflowExecutionSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function serialize(WorkflowExecution $execution): array
    {
        return [
            'id' => $execution->getId(),
            'workflow' => [
                'id' => $execution->getWorkflow()->getId(),
                'name' => $execution->getWorkflow()->getName(),
                'status' => $execution->getWorkflow()->getStatus()->value,
            ],
            'input_data' => $execution->getInputData(),
            'output_data' => $execution->getOutputData(),
            'status' => $execution->getStatus()->value,
            'started_at' => $execution->getStartedAt()?->format(\DATE_ATOM),
            'completed_at' => $execution->getCompletedAt()?->format(\DATE_ATOM),
            'error_message' => $execution->getErrorMessage(),
            'execution_log' => $execution->getExecutionLog(),
            'created_at' => $execution->getCreatedAt()->format(\DATE_ATOM),
        ];
    }
}
