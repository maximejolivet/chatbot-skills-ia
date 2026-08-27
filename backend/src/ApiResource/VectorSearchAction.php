<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Controller\VectorSearchController;

/**
 * The one canonical vector search endpoint. Not a Doctrine entity -- the
 * controller reads/validates the request body itself and returns a plain
 * JsonResponse.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/vector/search',
            controller: VectorSearchController::class,
            output: false,
            read: false,
            deserialize: false,
            name: 'vector_search',
            openapi: new OpenApiOperation(
                tags: ['Vector Search'],
                summary: 'Run a vector search against the knowledge base',
                description: 'Validates the request against VectorSearchRequest, then queries Qdrant with optional equality filters on category/document_type/language/complexity.',
                requestBody: new RequestBody(
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
                            'type' => 'object',
                            'properties' => [
                                'query' => ['type' => 'string', 'maxLength' => 500],
                                'collection_name' => ['type' => 'string', 'maxLength' => 100, 'nullable' => true],
                                'category_id' => ['type' => 'integer', 'nullable' => true],
                                'document_type' => ['type' => 'string', 'maxLength' => 100, 'nullable' => true],
                                'language' => ['type' => 'string', 'maxLength' => 10, 'nullable' => true],
                                'complexity' => ['type' => 'string', 'maxLength' => 50, 'nullable' => true],
                                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10],
                            ],
                            'required' => ['query'],
                        ])),
                    ]),
                ),
                responses: [
                    '200' => new OpenApiResponse(description: 'Search results.', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
                            'type' => 'object',
                            'properties' => [
                                'query' => ['type' => 'string'],
                                'results' => ['type' => 'array', 'items' => ['type' => 'object']],
                                'total' => ['type' => 'integer'],
                            ],
                        ])),
                    ])),
                    '400' => new OpenApiResponse(description: 'Request validation failed (see VectorSearchRequest constraints).', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object', 'properties' => ['errors' => ['type' => 'object']]])),
                    ])),
                    '401' => new OpenApiResponse(description: 'Not authenticated.'),
                    '403' => new OpenApiResponse(description: 'Authenticated but not ROLE_ADMIN.'),
                ],
            ),
        ),
    ],
)]
final class VectorSearchAction {}
