<?php

declare(strict_types=1);

namespace App\Chat;

use App\AiProvider\Client\ChatMessage;
use App\AiProvider\ProviderSelectionService;
use Psr\Log\LoggerInterface;

/**
 * Suggests 2-3 short follow-up questions after an assistant reply, grounded
 * in that actual exchange (itself already RAG-grounded, see
 * RagContextService) rather than the static FAQ list useFaqs.ts falls back
 * to for the empty-state screen. Uses the dedicated analysis LLM client
 * (same one DocumentAnalysisService uses) -- a cheap, non-critical-path
 * call that must never block or fail the real chat reply it runs after.
 */
final readonly class FollowUpQuestionsService
{
    private const int MAX_QUESTIONS = 3;
    private const int MAX_QUESTION_LENGTH = 100;

    public function __construct(
        private ProviderSelectionService $providerSelectionService,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array<int, string>
     */
    public function generate(string $userMessage, string $assistantReply): array
    {
        if ('' === trim($userMessage) || '' === trim($assistantReply)) {
            return [];
        }

        try {
            $result = $this->providerSelectionService->getAnalysisLlmClient()->complete(
                messages: [new ChatMessage(role: 'user', content: $this->buildPrompt($userMessage, $assistantReply))],
                temperature: 0.5,
                // Generous despite the tiny expected output: reasoning models
                // (e.g. gpt-oss:20b, this app's default analysis model) spend
                // most of the budget on hidden chain-of-thought before ever
                // emitting the JSON -- measured ~800 tokens end-to-end for a
                // 2-3 question answer. A tight cap truncates mid-thought,
                // before `content` is ever written, and parseQuestions()
                // silently returns [] (right behavior for a non-critical
                // side task, but confusing to debug without this context).
                maxTokens: 1200,
            );

            return $this->parseQuestions($result->message->content);
        } catch (\Throwable $e) {
            $this->logger->warning('Error generating follow-up questions: {error}', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function buildPrompt(string $userMessage, string $assistantReply): string
    {
        // Bounded: this is a lightweight side-task, not the real answer --
        // no need for the full reply if it happens to be very long.
        $replyPreview = mb_strlen($assistantReply) > 1500 ? mb_substr($assistantReply, 0, 1500) . '...' : $assistantReply;

        return <<<PROMPT
            Voici le dernier échange d'une conversation avec un assistant IA.

            Question du visiteur : {$userMessage}
            Réponse de l'assistant : {$replyPreview}

            Propose entre 2 et 3 questions de relance courtes (moins de 100 caractères
            chacune) que le visiteur pourrait poser ensuite, dans la continuité naturelle
            de cet échange. Pas de numérotation, pas de guillemets.

            Réponds UNIQUEMENT avec un objet JSON de cette forme, sans texte autour :
            {"questions": ["...", "..."]}
            PROMPT;
    }

    /**
     * @return array<int, string>
     */
    private function parseQuestions(string $text): array
    {
        $jsonStart = strpos($text, '{');
        $jsonEnd = strrpos($text, '}');
        if (false === $jsonStart || false === $jsonEnd || $jsonEnd <= $jsonStart) {
            $this->logger->warning('Could not extract JSON from follow-up questions response');

            return [];
        }

        $decoded = json_decode(substr($text, $jsonStart, $jsonEnd - $jsonStart + 1), true);
        if (\JSON_ERROR_NONE !== json_last_error() || !is_array($decoded) || !is_array($decoded['questions'] ?? null)) {
            return [];
        }

        $questions = [];
        foreach ($decoded['questions'] as $question) {
            if (!is_string($question)) {
                continue;
            }
            $question = trim($question);
            if ('' === $question) {
                continue;
            }
            $questions[] = mb_strlen($question) > self::MAX_QUESTION_LENGTH
                ? mb_substr($question, 0, self::MAX_QUESTION_LENGTH - 1) . '…'
                : $question;
            if (count($questions) >= self::MAX_QUESTIONS) {
                break;
            }
        }

        return $questions;
    }
}
