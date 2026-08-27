<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Controller\LlmStatusController;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/chat/llm-status',
            controller: LlmStatusController::class,
            output: false,
            read: false,
            name: 'chat_llm_status',
            openapi: new OpenApiOperation(
                tags: ['Chat'],
                summary: 'Check the chat LLM provider status',
                description: 'Live-checks the currently configured chat completion provider (Ollama or an OpenAI-compatible endpoint).',
                responses: [
                    '200' => new OpenApiResponse(description: 'Provider status.', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                    ])),
                    '401' => new OpenApiResponse(description: 'Not authenticated.'),
                    '500' => new OpenApiResponse(description: 'Failed to check LLM status.', content: new \ArrayObject([
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
final class LlmStatusAction {}
