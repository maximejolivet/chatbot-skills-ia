<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Enum\MessageRole;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
final class ConversationSourcesController
{
    #[IsGranted('OWNER', subject: 'data')]
    public function __invoke(Conversation $data): JsonResponse
    {
        $sources = [];

        // Extract sources from assistant messages metadata
        foreach ($data->getMessages() as $message) {
            if ($message->getRole() !== MessageRole::Assistant) {
                continue;
            }

            $metadata = $message->getMetadata();
            if (!empty($metadata['sources']) && is_array($metadata['sources'])) {
                foreach ($metadata['sources'] as $source) {
                    $sources[] = [
                        'content' => $source['content'] ?? '',
                        'source_url' => $source['source_url'] ?? '',
                        'score' => $source['score'] ?? 0.0,
                        'document_id' => $source['document_id'] ?? null,
                        'message_id' => $message->getId(),
                        'message_created_at' => $message->getCreatedAt()->format(\DATE_ATOM),
                    ];
                }
            }
        }

        return new JsonResponse([
            'conversation_id' => $data->getId(),
            'sources' => $sources,
            'total' => count($sources),
        ]);
    }
}