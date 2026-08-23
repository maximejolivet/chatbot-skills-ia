<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Controller\EmbeddingStatusController;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/chat/embedding-status',
            controller: EmbeddingStatusController::class,
            output: false,
            read: false,
            name: 'chat_embedding_status',
        ),
    ],
)]
final class EmbeddingStatusAction {}
