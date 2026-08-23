<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Indexed = 'indexed';
    case Error = 'error';
}
