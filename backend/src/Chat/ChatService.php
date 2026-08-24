<?php

namespace App\Chat;

use App\AiProvider\Client\ChatMessage as LlmChatMessage;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Enum\MessageRole;
use App\Repository\AiAgentRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Thin façade over ChatOrchestrationService for the two entry points:
 * authenticated conversations (persisted) and anonymous quick-send (not).
 */
final readonly class ChatService
{
    public function __construct(
        private MessageRepository $messageRepository,
        private AiAgentRepository $agentRepository,
        private ChatOrchestrationService $orchestrator,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private ConversationHistoryCache $historyCache,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private string $mailerFromAddress,
        #[Autowire(env: 'OWNER_NOTIFICATION_EMAIL')]
        private string $ownerNotificationEmail,
    ) {}

    /**
     * @param (callable(string): void)|null $onDelta    see ChatOrchestrationService::generateReply()
     * @param (callable(string): void)|null $onToolCall see ChatOrchestrationService::generateReply()
     */
    public function sendMessage(Conversation $conversation, string $userMessage, ?int $agentId = null, ?callable $onDelta = null, ?callable $onToolCall = null): Message
    {
        $userMsg = new Message()->setRole(MessageRole::User)->setContent($userMessage);
        $conversation->addMessage($userMsg);
        $this->entityManager->persist($userMsg);
        $this->entityManager->flush();

        if (1 === $conversation->getMessages()->count()) {
            $this->notifyNewConversation($conversation, $userMessage);
        }

        $agent = $agentId ? $this->agentRepository->getActive($agentId) : null;
        $history = $this->historyAsChatMessages($conversation->getId());

        $result = $this->orchestrator->generateReply($userMessage, $history, $agent, $conversation, $onDelta, $onToolCall);

        $assistantMsg = new Message()
            ->setRole(MessageRole::Assistant)
            ->setContent($result->content)
            ->setMetadata(['token_usage' => $result->usage, 'tool_calls' => $result->toolCalls, 'sources' => $result->sources]);
        $conversation->addMessage($assistantMsg);
        $this->entityManager->persist($assistantMsg);
        $this->entityManager->flush();

        $this->historyCache->invalidate($conversation->getId());

        return $assistantMsg;
    }

    public function quickSend(string $userMessage, ?int $agentId = null): ChatReplyResult
    {
        $agent = $agentId ? $this->agentRepository->getActive($agentId) : null;

        return $this->orchestrator->generateReply($userMessage, [], $agent);
    }

    /**
     * Best-effort ping the moment a visitor's first message lands -- lets
     * Maxime jump into a live conversation, not just learn about it after an
     * interview gets booked (see the "planifier_entretien" workflow, which
     * sends its own separate confirmation). Never breaks the chat reply if
     * the mailer is down/misconfigured.
     */
    private function notifyNewConversation(Conversation $conversation, string $firstMessage): void
    {
        if ('' === $this->ownerNotificationEmail) {
            return;
        }

        $email = new Email()
            ->from($this->mailerFromAddress)
            ->to($this->ownerNotificationEmail)
            ->subject('Nouvelle conversation sur le chatbot')
            ->text(
                "Un visiteur vient de démarrer une conversation (#{$conversation->getId()}).\n\n"
                . "Premier message :\n{$firstMessage}",
            );

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send new-conversation notification: {error}', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return LlmChatMessage[]
     */
    private function historyAsChatMessages(int $conversationId): array
    {
        return $this->historyCache->remember(
            $conversationId,
            fn(): array => array_map(
                static fn(Message $m): LlmChatMessage => new LlmChatMessage(role: $m->getRole()->value, content: $m->getContent()),
                $this->messageRepository->recentHistory($conversationId),
            ),
        );
    }
}
