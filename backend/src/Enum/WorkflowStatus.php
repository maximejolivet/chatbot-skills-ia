<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkflowStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Draft = 'draft';
}
