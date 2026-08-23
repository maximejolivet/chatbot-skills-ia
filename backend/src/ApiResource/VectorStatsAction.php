<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Controller\VectorStatsController;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/vector/stats',
            controller: VectorStatsController::class,
            output: false,
            read: false,
            name: 'vector_stats',
        ),
    ],
)]
final class VectorStatsAction {}
