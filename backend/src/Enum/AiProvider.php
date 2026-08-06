<?php

namespace App\Enum;

enum AiProvider: string
{
    case Ollama = 'ollama';
    case ApiEndpoint = 'api_endpoint';
}
