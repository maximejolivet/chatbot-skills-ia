<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
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
        ),
    ],
)]
final class VectorSearchAction {}
