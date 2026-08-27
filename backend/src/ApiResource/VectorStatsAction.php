<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Controller\VectorStatsController;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/vector/stats',
            controller: VectorStatsController::class,
            output: false,
            read: false,
            name: 'vector_stats',
            openapi: new OpenApiOperation(
                tags: ['Vector Search'],
                summary: 'Vector search usage statistics',
                description: 'Number of active vector indexes, total search queries recorded, and the 10 most recent queries.',
                responses: [
                    '200' => new OpenApiResponse(description: 'Vector search statistics.', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                    ])),
                    '401' => new OpenApiResponse(description: 'Not authenticated.'),
                    '403' => new OpenApiResponse(description: 'Authenticated but not ROLE_ADMIN.'),
                ],
            ),
        ),
    ],
)]
final class VectorStatsAction {}
