<?php

namespace App\Chat;

use App\AiProvider\Client\ChatMessage as LlmChatMessage;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Enum\MessageRole;
use App\Repository\AiAgentRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Thin façade over ChatOrchestrationService for the two entry points:
 * authenticated conversations (persisted) and anonymous quick-send (not).
 */
final class ChatService
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly AiAgentRepository $agentRepository,
        private readonly ChatOrchestrationService $orchestrator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function sendMessage(Conversation $conversation, string $userMessage, ?int $agentId = null): Message
    {
        $userMsg = (new Message())->setRole(MessageRole::User)->setContent($userMessage);
        $conversation->addMessage($userMsg);
        $this->entityManager->persist($userMsg);
        $this->entityManager->flush();

        $agent = $agentId ? $this->agentRepository->getActive($agentId) : null;
        $history = $this->historyAsChatMessages($conversation->getId());

        $result = $this->orchestrator->generateReply($userMessage, $history, $agent, $conversation);

        $assistantMsg = (new Message())
            ->setRole(MessageRole::Assistant)
            ->setContent($result->content)
            ->setMetadata(['token_usage' => $result->usage, 'tool_calls' => $result->toolCalls]);
        $conversation->addMessage($assistantMsg);
        $this->entityManager->persist($assistantMsg);
        $this->entityManager->flush();

        return $assistantMsg;
    }

    public function quickSend(string $userMessage, ?int $agentId = null): ChatReplyResult
    {
        $agent = $agentId ? $this->agentRepository->getActive($agentId) : null;

        return $this->orchestrator->generateReply($userMessage, [], $agent);
    }

    /**
     * @return LlmChatMessage[]
     */
    private function historyAsChatMessages(int $conversationId): array
    {
        return array_map(
            static fn (Message $m) => new LlmChatMessage(role: $m->getRole()->value, content: $m->getContent()),
            $this->messageRepository->recentHistory($conversationId),
        );
    }
}
