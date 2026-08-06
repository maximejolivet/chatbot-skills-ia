<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Controller\VectorStatsController;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/vector/stats',
            controller: VectorStatsController::class,
            read: false,
            output: false,
            name: 'vector_stats',
        ),
    ],
)]
final class VectorStatsAction
{
}
