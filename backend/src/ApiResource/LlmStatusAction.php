<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Controller\LlmStatusController;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/chat/llm-status',
            controller: LlmStatusController::class,
            output: false,
            read: false,
            name: 'chat_llm_status',
        ),
    ],
)]
final class LlmStatusAction {}
