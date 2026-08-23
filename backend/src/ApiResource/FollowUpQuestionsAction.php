<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
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
        ),
    ],
)]
final class FollowUpQuestionsAction {}
