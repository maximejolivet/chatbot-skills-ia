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
        $metadata = $message->getMetadata();

        // Add flag to indicate sources may be hidden on frontend
        $metadata['sources_hidden'] = true;

        return [
            'id' => $message->getId(),
            'role' => $message->getRole()->value,
            'content' => $message->getContent(),
            'created_at' => $message->getCreatedAt()->format(\DATE_ATOM),
            'metadata' => $metadata,
            'feedback' => $message->getFeedback()?->value,
        ];
    }
}
