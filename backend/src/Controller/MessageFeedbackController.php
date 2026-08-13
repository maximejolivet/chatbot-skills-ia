<?php

namespace App\Controller;

use App\Chat\MessageSerializer;
use App\Entity\Conversation;
use App\Enum\MessageFeedback;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Thumbs up/down on a single assistant message. Message has no #[ApiResource]
 * of its own (see MessageSerializer), so this is nested under the owning
 * Conversation, same pattern as ConversationMessagesController/
 * ConversationStreamController -- including the #[IsGranted] rather than a
 * declarative `security:` (see those two controllers for why).
 */
#[AsController]
final class MessageFeedbackController
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[IsGranted('OWNER', subject: 'data')]
    public function __invoke(Conversation $data, Request $request): JsonResponse
    {
        $message = $this->messageRepository->find((int) $request->attributes->get('messageId'));
        if (!$message || $message->getConversation()->getId() !== $data->getId()) {
            throw new NotFoundHttpException('Message not found in this conversation.');
        }

        $body = json_decode($request->getContent(), true) ?? [];
        if (!\array_key_exists('feedback', $body)) {
            throw new BadRequestHttpException('Missing feedback.');
        }

        $value = $body['feedback'];
        if (null === $value) {
            $message->setFeedback(null);
        } else {
            $feedback = MessageFeedback::tryFrom((string) $value);
            if (!$feedback) {
                throw new BadRequestHttpException("Invalid feedback value '{$value}', expected 'positive', 'negative' or null.");
            }
            $message->setFeedback($feedback);
        }

        $this->entityManager->flush();

        return new JsonResponse(MessageSerializer::serialize($message));
    }
}
