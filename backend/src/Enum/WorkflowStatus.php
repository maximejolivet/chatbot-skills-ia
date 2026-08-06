<?php

namespace App\Enum;

enum WorkflowStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Draft = 'draft';
}
