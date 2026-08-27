<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Controller\EmbeddingStatusController;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/chat/embedding-status',
            controller: EmbeddingStatusController::class,
            output: false,
            read: false,
            name: 'chat_embedding_status',
            openapi: new OpenApiOperation(
                tags: ['Chat'],
                summary: 'Check the embedding provider status',
                description: 'Live-checks the currently configured embedding provider.',
                responses: [
                    '200' => new OpenApiResponse(description: 'Provider status.', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                    ])),
                    '401' => new OpenApiResponse(description: 'Not authenticated.'),
                    '500' => new OpenApiResponse(description: 'Failed to check embedding status.', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
                            'type' => 'object',
                            'properties' => ['error' => ['type' => 'string'], 'status' => ['type' => 'string']],
                        ])),
                    ])),
                ],
            ),
        ),
    ],
)]
final class EmbeddingStatusAction {}
