<?php

namespace App\Message;

/**
 * Run an already-created (status 'pending') WorkflowExecution in the
 * background (see ExecuteWorkflowMessageHandler). Only for the externally
 * triggered paths (WorkflowTriggerController/WorkflowTestController) --
 * tool-calling-triggered executions run inline via
 * WorkflowExecutionService::execute(), never through this message.
 */
final readonly class ExecuteWorkflowMessage
{
    public function __construct(
        public int $workflowExecutionId,
    ) {
    }
}
