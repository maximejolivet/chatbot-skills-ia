<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Controller\HealthController;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/health',
            controller: HealthController::class,
            output: false,
            read: false,
            name: 'health_check',
            openapi: new OpenApiOperation(
                tags: ['Health'],
                summary: 'Aggregated health check',
                description: 'Checks database, Qdrant, Redis and the LLM provider in one call. Each check is independent and best-effort -- a failing service is reported as status "error" for that entry, never a 500.',
                responses: [
                    '200' => new OpenApiResponse(description: 'All checks passed.', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                    ])),
                    '401' => new OpenApiResponse(description: 'Not authenticated.'),
                    '503' => new OpenApiResponse(description: 'At least one check reported an error (status: "degraded").', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                    ])),
                ],
            ),
        ),
    ],
)]
final class HealthAction {}
