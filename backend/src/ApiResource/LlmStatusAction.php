<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Controller\LlmStatusController;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/chat/llm-status',
            controller: LlmStatusController::class,
            read: false,
            output: false,
            name: 'chat_llm_status',
        ),
    ],
)]
final class LlmStatusAction
{
}
