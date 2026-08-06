<?php

namespace App\Enum;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';
    case Tool = 'tool';
}
