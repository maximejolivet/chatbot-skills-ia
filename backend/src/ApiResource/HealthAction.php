<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Controller\HealthController;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/health',
            controller: HealthController::class,
            output: false,
            read: false,
            name: 'health_check',
        ),
    ],
)]
final class HealthAction {}
