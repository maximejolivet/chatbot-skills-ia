<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkflowExecutionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
