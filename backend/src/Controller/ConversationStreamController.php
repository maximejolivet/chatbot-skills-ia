<?php

namespace App\Controller;

use App\Chat\ChatService;
use App\Chat\MessageSerializer;
use App\Entity\Conversation;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Relays real token-level deltas as `type: delta` SSE frames while the reply
 * is generated (ChatOrchestrationService streams whenever the agent has no
 * active tools; buffered otherwise, see that class), then `ai_complete` with
 * the full serialized message once persisted -- the frontend builds up the
 * bubble from deltas and only reads ai_complete for metadata (id, sources,
 * tool_calls, feedback), not for content, since a delta stream may have
 * already rendered it.
 */
#[AsController]
final readonly class ConversationStreamController
{
    public function __construct(
        private ChatService $chatService,
        #[Autowire(service: 'limiter.chat_message')]
        private RateLimiterFactory $chatMessageLimiter,
    ) {}

    // See ConversationMessagesController for why this is an #[IsGranted]
    // check rather than relying on ApiResource's `security:` alone.
    #[IsGranted('OWNER', subject: 'data')]
    public function __invoke(Conversation $data, Request $request): StreamedResponse
    {
        if (!$this->chatMessageLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'Too many messages, please slow down.');
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $userMessage = trim((string) ($body['message'] ?? ''));
        if ('' === $userMessage) {
            throw new BadRequestHttpException('Missing message.');
        }
        $agentId = isset($body['agent_id']) ? (int) $body['agent_id'] : null;

        $response = new StreamedResponse(function () use ($data, $userMessage, $agentId): void {
            $this->emit(['type' => 'user_message', 'content' => $userMessage]);

            try {
                $onDelta = function (string $chunk): void {
                    $this->emit(['type' => 'delta', 'content' => $chunk]);
                };
                $assistantMessage = $this->chatService->sendMessage($data, $userMessage, $agentId, $onDelta);
                $this->emit(['type' => 'ai_complete', 'done' => true, ...MessageSerializer::serialize($assistantMessage)]);
            } catch (\Throwable $e) {
                $this->emit(['type' => 'error', 'content' => $e->getMessage()]);
            }

            $this->emit(['type' => 'done', 'done' => true]);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function emit(array $payload): void
    {
        echo 'data: ' . json_encode($payload, \JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n\n";
        flush();
    }
}
