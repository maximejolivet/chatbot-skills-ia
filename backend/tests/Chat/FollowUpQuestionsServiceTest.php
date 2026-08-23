<?php

namespace App\Tests\Chat;

use App\AiProvider\ProviderSelectionService;
use App\Chat\FollowUpQuestionsService;
use App\Repository\AiProviderConfigRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * ProviderSelectionService is `final`, so it's a real instance here (wired
 * with an inert AiProviderConfigRepository stub) rather than a double --
 * same technique as VectorSearchServiceTest. Never actually reaches an LLM
 * in these tests: generate()'s empty-input guard returns before calling it,
 * and parseQuestions() (invoked via reflection, it's private) is pure
 * string/JSON parsing with no dependency on the client at all.
 */
final class FollowUpQuestionsServiceTest extends TestCase
{
    private function service(): FollowUpQuestionsService
    {
        $providerSelection = new ProviderSelectionService(
            $this->createStub(AiProviderConfigRepository::class),
            $this->createStub(LoggerInterface::class),
            'ollama',
            '',
            '',
            '',
            30,
            'http://ollama.test:11434',
            'chat-model',
            'embed-model',
            'analysis-model',
        );

        return new FollowUpQuestionsService($providerSelection, $this->createStub(LoggerInterface::class));
    }

    private function parseQuestions(string $text): array
    {
        $service = $this->service();

        return new \ReflectionMethod($service, 'parseQuestions')->invoke($service, $text);
    }

    public function testGenerateReturnsEmptyArrayForBlankUserMessage(): void
    {
        self::assertSame([], $this->service()->generate('   ', 'Une réponse.'));
    }

    public function testGenerateReturnsEmptyArrayForBlankAssistantReply(): void
    {
        self::assertSame([], $this->service()->generate('Une question ?', ''));
    }

    public function testParseQuestionsExtractsFromValidJson(): void
    {
        $result = $this->parseQuestions('{"questions": ["Et ensuite ?", "Autre chose ?"]}');

        self::assertSame(['Et ensuite ?', 'Autre chose ?'], $result);
    }

    public function testParseQuestionsExtractsJsonSurroundedByProse(): void
    {
        // Reasoning models sometimes wrap the JSON in explanatory text
        // despite the "UNIQUEMENT" instruction -- extraction must tolerate it.
        $result = $this->parseQuestions('Voici le résultat : {"questions": ["Une question ?"]} Voilà.');

        self::assertSame(['Une question ?'], $result);
    }

    public function testParseQuestionsReturnsEmptyArrayWithoutJsonBraces(): void
    {
        self::assertSame([], $this->parseQuestions('Désolé, je ne peux pas répondre à cela.'));
    }

    public function testParseQuestionsReturnsEmptyArrayOnInvalidJson(): void
    {
        self::assertSame([], $this->parseQuestions('{"questions": [invalid}'));
    }

    public function testParseQuestionsReturnsEmptyArrayWhenQuestionsIsNotAnArray(): void
    {
        self::assertSame([], $this->parseQuestions('{"questions": "not an array"}'));
    }

    public function testParseQuestionsFiltersNonStringAndBlankEntries(): void
    {
        $result = $this->parseQuestions('{"questions": ["Valide ?", 42, "  ", null, "Aussi valide ?"]}');

        self::assertSame(['Valide ?', 'Aussi valide ?'], $result);
    }

    public function testParseQuestionsCapsAtThreeQuestions(): void
    {
        $result = $this->parseQuestions('{"questions": ["Q1", "Q2", "Q3", "Q4", "Q5"]}');

        self::assertCount(3, $result);
        self::assertSame(['Q1', 'Q2', 'Q3'], $result);
    }

    public function testParseQuestionsTruncatesOverlyLongQuestions(): void
    {
        $longQuestion = str_repeat('a', 150);

        $result = $this->parseQuestions('{"questions": ["' . $longQuestion . '"]}');

        self::assertCount(1, $result);
        self::assertSame(100, mb_strlen($result[0]));
        self::assertStringEndsWith('…', $result[0]);
    }
}
