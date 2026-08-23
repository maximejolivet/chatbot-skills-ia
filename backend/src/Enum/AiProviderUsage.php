<?php

declare(strict_types=1);

namespace App\Enum;

enum AiProviderUsage: string
{
    case Chat = 'chat';
    case Embedding = 'embedding';
}
