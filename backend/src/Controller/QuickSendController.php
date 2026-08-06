<?php

namespace App\Controller;

use App\Chat\ChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Anonymous, single-turn chat -- what the 3 demo frontend widgets use. No
 * conversation is persisted, but tool-calling still runs.
 */
#[AsController]
final class QuickSendController
{
    public function __construct(private readonly ChatService $chatService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
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
        ]);
    }
}
