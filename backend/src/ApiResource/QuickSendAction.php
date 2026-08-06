<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\QuickSendController;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/chat/quick-send',
            controller: QuickSendController::class,
            read: false,
            deserialize: false,
            output: false,
            name: 'chat_quick_send',
        ),
    ],
)]
final class QuickSendAction
{
}
