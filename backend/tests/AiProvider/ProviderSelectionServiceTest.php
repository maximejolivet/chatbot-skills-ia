<?php

namespace App\Tests\AiProvider;

use App\AiProvider\Client\ApiEndpoint\OpenAiCompatibleEmbeddingClient;
use App\AiProvider\Client\ApiEndpoint\OpenAiCompatibleLlmClient;
use App\AiProvider\Client\FallbackLlmClient;
use App\AiProvider\Client\Ollama\OllamaEmbeddingClient;
use App\AiProvider\Client\Ollama\OllamaLlmClient;
use App\AiProvider\ProviderSelectionService;
use App\Entity\AiProviderConfig;
use App\Enum\AiProvider;
use App\Enum\AiProviderUsage;
use App\Repository\AiProviderConfigRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Selection rule under test: an active AiProviderConfig row always takes
 * priority over the env-var fallback; zero/one/many active rows each take a
 * different path (env fallback, single client, FallbackLlmClient chain).
 */
final class ProviderSelectionServiceTest extends TestCase
{
    /**
     * @param AiProviderConfig[] $activeChatConfigs
     */
    private function service(array $activeChatConfigs = [], ?AiProviderConfig $activeEmbeddingConfig = null, string $aiProvider = 'ollama', string $aiApiKey = ''): ProviderSelectionService
    {
        $repository = $this->createStub(AiProviderConfigRepository::class);
        $repository->method('getAllActiveForUsage')->willReturn($activeChatConfigs);
        $repository->method('getActiveForUsage')->willReturn($activeEmbeddingConfig);

        return new ProviderSelectionService(
            $repository,
            $this->createStub(LoggerInterface::class),
            $aiProvider,
            'https://api.example.test/v1/chat/completions',
            $aiApiKey,
            'gpt-test',
            30,
            'http://ollama.test:11434',
            'ollama-chat-model',
            'ollama-embed-model',
            'ollama-analysis-model',
        );
    }

    private static int $configCounter = 0;

    private function config(AiProvider $provider, bool $isDefault = false): AiProviderConfig
    {
        return new AiProviderConfig()
            ->setName('config-' . ++self::$configCounter)
            ->setUsage(AiProviderUsage::Chat)
            ->setProvider($provider)
            ->setApiEndpoint('https://configured.example.test')
            ->setApiKey('configured-key')
            ->setModel('configured-model')
            ->setIsActive(true)
            ->setIsDefault($isDefault);
    }

    public function testNoActiveConfigFallsBackToOllamaEnvProvider(): void
    {
        $client = $this->service(aiProvider: 'ollama')->getLlmClient();

        self::assertInstanceOf(OllamaLlmClient::class, $client);
        self::assertSame('ollama-chat-model', $client->model);
    }

    public function testNoActiveConfigFallsBackToApiEndpointEnvProvider(): void
    {
        $client = $this->service(aiProvider: 'api_endpoint', aiApiKey: 'env-key')->getLlmClient();

        self::assertInstanceOf(OpenAiCompatibleLlmClient::class, $client);
    }

    public function testNoActiveConfigAndUnusableApiEndpointEnvFallsBackToOllama(): void
    {
        // AI_API_KEY empty -> OpenAiCompatibleLlmClient's constructor throws,
        // getLlmClient() must catch it and fall back to Ollama rather than
        // propagating the exception.
        $client = $this->service(aiProvider: 'api_endpoint', aiApiKey: '')->getLlmClient();

        self::assertInstanceOf(OllamaLlmClient::class, $client);
    }

    public function testSingleActiveConfigReturnsThatClientDirectly(): void
    {
        $client = $this->service([$this->config(AiProvider::Ollama)])->getLlmClient();

        self::assertInstanceOf(OllamaLlmClient::class, $client);
        self::assertSame('configured-model', $client->model);
    }

    public function testMultipleActiveConfigsReturnFallbackChain(): void
    {
        $client = $this->service([
            $this->config(AiProvider::Ollama, isDefault: true),
            $this->config(AiProvider::ApiEndpoint),
        ])->getLlmClient();

        self::assertInstanceOf(FallbackLlmClient::class, $client);
    }

    public function testUnusableConfigIsSkippedLeavingOthersUsable(): void
    {
        $unusable = new AiProviderConfig()
            ->setName('unusable')
            ->setUsage(AiProviderUsage::Chat)
            ->setProvider(AiProvider::ApiEndpoint)
            ->setApiKey('') // OpenAiCompatibleLlmClient throws on empty key
            ->setIsActive(true);

        $client = $this->service([$unusable, $this->config(AiProvider::Ollama)])->getLlmClient();

        // Only one config survived construction -> single client, not a
        // FallbackLlmClient wrapping a lone entry.
        self::assertInstanceOf(OllamaLlmClient::class, $client);
    }

    public function testEmbeddingClientPrefersActiveConfigOverEnv(): void
    {
        $config = new AiProviderConfig()
            ->setName('embed-config')
            ->setUsage(AiProviderUsage::Embedding)
            ->setProvider(AiProvider::ApiEndpoint)
            ->setApiEndpoint('https://configured.example.test')
            ->setApiKey('configured-key')
            ->setModel('configured-embed-model')
            ->setIsActive(true);

        $client = $this->service(activeEmbeddingConfig: $config)->getEmbeddingClient();

        self::assertInstanceOf(OpenAiCompatibleEmbeddingClient::class, $client);
    }

    public function testEmbeddingClientFallsBackToOllamaWithoutActiveConfig(): void
    {
        $client = $this->service()->getEmbeddingClient();

        self::assertInstanceOf(OllamaEmbeddingClient::class, $client);
        self::assertSame('ollama-embed-model', $client->model);
    }

    public function testAnalysisClientIsAlwaysOllamaWithAnalysisModel(): void
    {
        $client = $this->service()->getAnalysisLlmClient();

        self::assertInstanceOf(OllamaLlmClient::class, $client);
        self::assertSame('ollama-analysis-model', $client->model);
    }
}
