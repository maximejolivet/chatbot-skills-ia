<?php

namespace App\Chat;

use App\Entity\Message;

final class MessageSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function serialize(Message $message): array
    {
        return [
            'id' => $message->getId(),
            'role' => $message->getRole()->value,
            'content' => $message->getContent(),
            'created_at' => $message->getCreatedAt()->format(\DATE_ATOM),
            'metadata' => $message->getMetadata(),
        ];
    }
}
