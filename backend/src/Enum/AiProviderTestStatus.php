<?php

declare(strict_types=1);

namespace App\Enum;

enum AiProviderTestStatus: string
{
    case Unknown = 'unknown';
    case Success = 'success';
    case Error = 'error';
}
