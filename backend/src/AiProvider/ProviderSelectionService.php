<?php

namespace App\AiProvider;

use App\AiProvider\Client\ApiEndpoint\OpenAiCompatibleEmbeddingClient;
use App\AiProvider\Client\ApiEndpoint\OpenAiCompatibleLlmClient;
use App\AiProvider\Client\EmbeddingClientInterface;
use App\AiProvider\Client\LlmClientInterface;
use App\AiProvider\Client\Ollama\OllamaEmbeddingClient;
use App\AiProvider\Client\Ollama\OllamaLlmClient;
use App\Entity\AiProviderConfig;
use App\Enum\AiProvider;
use App\Enum\AiProviderUsage;
use App\Repository\AiProviderConfigRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provider selection -- the single implementation of "pick the right LLM/embedding
 * client." Selection rule: an active AiProviderConfig row for the requested usage
 * (admin-managed) always takes priority over the env-var fallback.
 */
final class ProviderSelectionService
{
    public function __construct(
        private readonly AiProviderConfigRepository $repository,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'AI_PROVIDER')]
        private readonly string $aiProvider,
        #[Autowire(env: 'AI_API_ENDPOINT')]
        private readonly string $aiApiEndpoint,
        #[Autowire(env: 'AI_API_KEY')]
        private readonly string $aiApiKey,
        #[Autowire(env: 'AI_API_MODEL')]
        private readonly string $aiApiModel,
        #[Autowire(env: 'int:AI_API_TIMEOUT')]
        private readonly int $aiApiTimeout,
        #[Autowire(env: 'OLLAMA_BASE_URL')]
        private readonly string $ollamaBaseUrl,
        #[Autowire(env: 'OLLAMA_CHAT_MODEL')]
        private readonly string $ollamaChatModel,
        #[Autowire(env: 'OLLAMA_EMBEDDING_MODEL')]
        private readonly string $ollamaEmbeddingModel,
        #[Autowire(env: 'OLLAMA_ANALYSIS_MODEL')]
        private readonly string $ollamaAnalysisModel,
    ) {
    }

    public function getLlmClient(AiProviderUsage $usage = AiProviderUsage::Chat): LlmClientInterface
    {
        $config = $this->repository->getActiveForUsage($usage);

        if (!$config) {
            $this->logger->info('No AiProviderConfig for usage "{usage}"; using env provider "{provider}".', [
                'usage' => $usage->value,
                'provider' => $this->aiProvider,
            ]);
            if ('api_endpoint' === strtolower($this->aiProvider)) {
                try {
                    return new OpenAiCompatibleLlmClient(
                        apiEndpoint: $this->aiApiEndpoint,
                        apiKey: $this->aiApiKey,
                        model: $this->aiApiModel,
                        timeout: $this->aiApiTimeout,
                    );
                } catch (\InvalidArgumentException $e) {
                    $this->logger->warning('API endpoint LLM client unavailable ({error}); falling back to Ollama.', ['error' => $e->getMessage()]);
                }
            }

            return new OllamaLlmClient(baseUrl: $this->ollamaBaseUrl, model: $this->ollamaChatModel);
        }

        $this->logger->info('Using AiProviderConfig "{name}" for {usage}.', ['name' => $config->getName(), 'usage' => $usage->value]);
        if (AiProvider::ApiEndpoint === $config->getProvider()) {
            try {
                return new OpenAiCompatibleLlmClient(
                    apiEndpoint: $config->getApiEndpoint() ?? $this->aiApiEndpoint,
                    apiKey: $config->getApiKey() ?? '',
                    model: $config->getModel() ?? $this->aiApiModel,
                    timeout: $this->aiApiTimeout,
                );
            } catch (\InvalidArgumentException $e) {
                $this->logger->warning('Configured API endpoint LLM client unavailable ({error}); falling back to Ollama.', ['error' => $e->getMessage()]);

                return new OllamaLlmClient(baseUrl: $config->getBaseUrl() ?? $this->ollamaBaseUrl, model: $this->ollamaChatModel);
            }
        }

        return new OllamaLlmClient(
            baseUrl: $config->getBaseUrl() ?? $this->ollamaBaseUrl,
            model: $config->getModel() ?? $this->ollamaChatModel,
        );
    }

    public function getEmbeddingClient(): EmbeddingClientInterface
    {
        $config = $this->repository->getActiveForUsage(AiProviderUsage::Embedding);

        if (!$config) {
            if ('api_endpoint' === strtolower($this->aiProvider) && $this->aiApiKey) {
                try {
                    return new OpenAiCompatibleEmbeddingClient(
                        apiEndpoint: $this->aiApiEndpoint,
                        apiKey: $this->aiApiKey,
                        model: $this->aiApiModel,
                    );
                } catch (\InvalidArgumentException $e) {
                    $this->logger->warning('API endpoint embedding client unavailable ({error}); falling back to Ollama.', ['error' => $e->getMessage()]);
                }
            }

            return new OllamaEmbeddingClient(baseUrl: $this->ollamaBaseUrl, model: $this->ollamaEmbeddingModel);
        }

        $this->logger->info('Using AiProviderConfig "{name}" for embedding.', ['name' => $config->getName()]);
        if (AiProvider::ApiEndpoint === $config->getProvider() && $config->getApiKey()) {
            try {
                return new OpenAiCompatibleEmbeddingClient(
                    apiEndpoint: $config->getApiEndpoint() ?? $this->aiApiEndpoint,
                    apiKey: $config->getApiKey(),
                    model: $config->getModel() ?? $this->aiApiModel,
                );
            } catch (\InvalidArgumentException $e) {
                $this->logger->warning('Configured API endpoint embedding client unavailable ({error}); falling back to Ollama.', ['error' => $e->getMessage()]);
            }
        }

        return new OllamaEmbeddingClient(
            baseUrl: $config->getBaseUrl() ?? $this->ollamaBaseUrl,
            model: $config->getModel() ?? $this->ollamaEmbeddingModel,
        );
    }

    /**
     * Build a client directly from a given config row (bypassing 'active' resolution) --
     * used by the live "test this endpoint" action.
     */
    public function buildLlmClientFromConfig(AiProviderConfig $config): LlmClientInterface
    {
        if (AiProvider::ApiEndpoint === $config->getProvider()) {
            return new OpenAiCompatibleLlmClient(
                apiEndpoint: $config->getApiEndpoint() ?? $this->aiApiEndpoint,
                apiKey: $config->getApiKey() ?? '',
                model: $config->getModel() ?? $this->aiApiModel,
                timeout: $this->aiApiTimeout,
            );
        }

        return new OllamaLlmClient(
            baseUrl: $config->getBaseUrl() ?? $this->ollamaBaseUrl,
            model: $config->getModel() ?? $this->ollamaChatModel,
        );
    }

    public function buildEmbeddingClientFromConfig(AiProviderConfig $config): EmbeddingClientInterface
    {
        if (AiProvider::ApiEndpoint === $config->getProvider()) {
            return new OpenAiCompatibleEmbeddingClient(
                apiEndpoint: $config->getApiEndpoint() ?? $this->aiApiEndpoint,
                apiKey: $config->getApiKey() ?? '',
                model: $config->getModel() ?? $this->aiApiModel,
            );
        }

        return new OllamaEmbeddingClient(
            baseUrl: $config->getBaseUrl() ?? $this->ollamaBaseUrl,
            model: $config->getModel() ?? $this->ollamaEmbeddingModel,
        );
    }

    /**
     * Document-analysis model. Ollama-only, env-driven -- there is deliberately
     * no 'analysis' AiProviderConfig usage.
     */
    public function getAnalysisLlmClient(): LlmClientInterface
    {
        return new OllamaLlmClient(baseUrl: $this->ollamaBaseUrl, model: $this->ollamaAnalysisModel);
    }

    /**
     * @return array<string, mixed>
     */
    public function checkLlmStatus(AiProviderUsage $usage = AiProviderUsage::Chat): array
    {
        return $this->getLlmClient($usage)->checkStatus();
    }

    /**
     * @return array<string, mixed>
     */
    public function checkEmbeddingStatus(): array
    {
        return $this->getEmbeddingClient()->checkStatus();
    }
}
