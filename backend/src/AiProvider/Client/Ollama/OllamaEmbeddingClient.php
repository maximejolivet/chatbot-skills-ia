<?php

namespace App\AiProvider\Client\Ollama;

use App\AiProvider\Client\EmbeddingClientInterface;
use App\AiProvider\Client\EmbeddingResult;
use App\AiProvider\Client\TokenEstimator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OllamaEmbeddingClient implements EmbeddingClientInterface
{
    private HttpClientInterface $httpClient;

    public function __construct(
        public string $baseUrl,
        public string $model,
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    public function embed(string $text): EmbeddingResult
    {
        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/api/embeddings', [
            'json' => ['model' => $this->model, 'prompt' => $text],
        ]);
        $data = $response->toArray();

        $promptTokens = $data['prompt_eval_count'] ?? null;
        $source = 'provider';
        if (null === $promptTokens) {
            $promptTokens = TokenEstimator::estimate($text);
            $source = 'estimated';
        }

        return new EmbeddingResult(
            vector: $data['embedding'],
            usage: [
                'prompt_tokens' => $promptTokens,
                'total_tokens' => $promptTokens,
                'source' => $source,
                'provider' => 'ollama',
                'model' => $this->model,
            ],
        );
    }

    public function embedBatch(array $texts): array
    {
        return array_map($this->embed(...), $texts);
    }

    public function checkStatus(): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/api/tags', ['timeout' => 5]);
            if (200 === $response->getStatusCode()) {
                return [
                    'status' => 'ok',
                    'provider' => 'ollama',
                    'base_url' => $this->baseUrl,
                    'model' => $this->model,
                ];
            }

            return [
                'status' => 'error',
                'provider' => 'ollama',
                'base_url' => $this->baseUrl,
                'message' => sprintf('HTTP %d', $response->getStatusCode()),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'provider' => 'ollama',
                'base_url' => $this->baseUrl,
                'message' => $e->getMessage(),
            ];
        }
    }
}
