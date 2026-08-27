<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Controller\FollowUpQuestionsController;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/chat/follow-up-questions',
            controller: FollowUpQuestionsController::class,
            output: false,
            read: false,
            deserialize: false,
            name: 'chat_follow_up_questions',
            openapi: new OpenApiOperation(
                tags: ['Chat'],
                summary: 'Generate follow-up question suggestions',
                description: 'Stateless: takes the Q&A pair the caller already has in hand (no persisted Conversation lookup) and suggests follow-up questions. Rate-limited per client IP.',
                requestBody: new RequestBody(
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string', 'description' => 'The user message that was asked.'],
                                'answer' => ['type' => 'string', 'description' => 'The assistant reply that was given.'],
                            ],
                            'required' => ['message', 'answer'],
                        ])),
                    ]),
                ),
                responses: [
                    '200' => new OpenApiResponse(description: 'Suggested follow-up questions.', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
                            'type' => 'object',
                            'properties' => ['questions' => ['type' => 'array', 'items' => ['type' => 'string']]],
                        ])),
                    ])),
                    '400' => new OpenApiResponse(description: 'Missing message or answer.'),
                    '401' => new OpenApiResponse(description: 'Not authenticated.'),
                    '429' => new OpenApiResponse(description: 'Too many messages sent too quickly; slow down.'),
                ],
            ),
        ),
    ],
)]
final class FollowUpQuestionsAction {}
