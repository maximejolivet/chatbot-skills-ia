<?php

namespace App\Enum;

enum AiProviderUsage: string
{
    case Chat = 'chat';
    case Embedding = 'embedding';
}
