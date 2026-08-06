<?php

namespace App\Enum;

enum AiProviderTestStatus: string
{
    case Unknown = 'unknown';
    case Success = 'success';
    case Error = 'error';
}
