<?php

namespace App\Controller;

use App\Chat\ChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Anonymous, single-turn chat, no conversation persisted (tool-calling still
 * runs). NOT used by this repo's own ChatWidget/useChatbot.ts (which talks
 * to real, persisted Conversations via ConversationMessagesController) --
 * kept as a lighter-weight entry point for other embedders of the reusable
 * widget who don't need history.
 */
#[AsController]
final class QuickSendController
{
    public function __construct(
        private readonly ChatService $chatService,
        #[Autowire(service: 'limiter.chat_message')]
        private readonly RateLimiterFactory $chatMessageLimiter,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->chatMessageLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'Too many messages, please slow down.');
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $message = trim((string) ($body['message'] ?? ''));
        if ('' === $message) {
            throw new BadRequestHttpException('Missing message.');
        }
        $agentId = isset($body['agent_id']) ? (int) $body['agent_id'] : null;

        try {
            $result = $this->chatService->quickSend($message, $agentId);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => "Error generating AI response: {$e->getMessage()}"], 500);
        }

        return new JsonResponse([
            'response' => $result->content,
            'conversation_id' => null,
            'status' => 'success',
            'token_usage' => $result->usage,
            'tool_calls' => $result->toolCalls,
            'sources' => $result->sources,
        ]);
    }
}
