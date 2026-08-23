<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
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
        ),
    ],
)]
final class QuickSendAction {}
