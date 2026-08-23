<?php

namespace App\Controller;

use App\Chat\FollowUpQuestionsService;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Stateless, self-contained like QuickSendController: takes the Q&A pair the
 * frontend already has in hand (see useChatbot.ts::sendMessage) instead of
 * looking up a persisted Conversation/Message, so the widget can fetch
 * follow-ups for a reply as soon as it lands, without a round trip to
 * resolve IDs first. Called *after* the real reply is already shown --
 * never on the critical path of a chat turn, see FollowUpQuestionsService.
 */
#[AsController]
final readonly class FollowUpQuestionsController
{
    public function __construct(
        private FollowUpQuestionsService $followUpQuestionsService,
        #[Autowire(service: 'limiter.chat_message')]
        private RateLimiterFactory $chatMessageLimiter,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->chatMessageLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'Too many messages, please slow down.');
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $message = trim((string) ($body['message'] ?? ''));
        $answer = trim((string) ($body['answer'] ?? ''));
        if ('' === $message || '' === $answer) {
            throw new BadRequestHttpException('Missing message or answer.');
        }

        return new JsonResponse([
            'questions' => $this->followUpQuestionsService->generate($message, $answer),
        ]);
    }
}
