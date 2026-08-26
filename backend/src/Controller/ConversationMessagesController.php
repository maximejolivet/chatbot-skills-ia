<?php

namespace App\Controller;

use App\Chat\ChatService;
use App\Chat\MessageSerializer;
use App\Entity\Conversation;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
final readonly class ConversationMessagesController
{
    public function __construct(
        private ChatService $chatService,
        #[Autowire(service: 'limiter.chat_message')]
        private RateLimiterFactory $chatMessageLimiter,
    ) {}

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

        if (!$this->chatMessageLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'Too many messages, please slow down.');
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
