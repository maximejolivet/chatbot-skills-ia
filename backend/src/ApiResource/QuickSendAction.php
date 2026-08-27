<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Controller\QuickSendController;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/chat/quick-send',
            controller: QuickSendController::class,
            output: false,
            read: false,
            deserialize: false,
            name: 'chat_quick_send',
            openapi: new OpenApiOperation(
                tags: ['Chat'],
                summary: 'Anonymous, single-turn chat message',
                description: 'No Conversation is persisted (tool-calling still runs). A lighter-weight entry point for embedders that do not need history; this repo\'s own widget uses conversation_messages_send instead. Rate-limited per client IP.',
                requestBody: new RequestBody(
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string'],
                                'agent_id' => ['type' => 'integer', 'nullable' => true],
                            ],
                            'required' => ['message'],
                        ])),
                    ]),
                ),
                responses: [
                    '200' => new OpenApiResponse(description: 'The assistant reply (sources included but flagged sources_hidden for public-facing consumers).', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                    ])),
                    '400' => new OpenApiResponse(description: 'Missing message.'),
                    '401' => new OpenApiResponse(description: 'Not authenticated.'),
                    '429' => new OpenApiResponse(description: 'Too many messages sent too quickly; slow down.'),
                    '500' => new OpenApiResponse(description: 'Error generating the AI response.', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object', 'properties' => ['error' => ['type' => 'string']]])),
                    ])),
                ],
            ),
        ),
    ],
)]
final class QuickSendAction {}
