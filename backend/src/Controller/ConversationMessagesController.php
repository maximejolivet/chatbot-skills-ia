<?php

namespace App\Controller;

use App\Chat\ChatService;
use App\Chat\MessageSerializer;
use App\Entity\Conversation;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsController]
final class ConversationMessagesController
{
    public function __construct(private readonly ChatService $chatService)
    {
    }

    public function __invoke(Conversation $data, Request $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            return new JsonResponse(array_map(MessageSerializer::serialize(...), $data->getMessages()->toArray()));
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $message = trim((string) ($body['message'] ?? ''));
        if ('' === $message) {
            throw new BadRequestHttpException('Missing message.');
        }
        $agentId = isset($body['agent_id']) ? (int) $body['agent_id'] : null;

        $assistantMessage = $this->chatService->sendMessage($data, $message, $agentId);

        return new JsonResponse(MessageSerializer::serialize($assistantMessage), 201);
    }
}
