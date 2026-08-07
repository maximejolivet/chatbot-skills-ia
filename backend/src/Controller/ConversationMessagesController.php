<?php

namespace App\Controller;

use App\Chat\ChatService;
use App\Chat\MessageSerializer;
use App\Entity\Conversation;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
final class ConversationMessagesController
{
    public function __construct(private readonly ChatService $chatService)
    {
    }

    // ApiResource's declarative `security: "is_granted('OWNER', object)"` on
    // this operation is NOT reliably enforced for custom-controller (read:
    // true + controller:) operations -- verified empirically, the voter
    // never runs. #[IsGranted] on the controller itself always does, via
    // Symfony's own IsGrantedAttributeListener on kernel.controller.
    #[IsGranted('OWNER', subject: 'data')]
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
