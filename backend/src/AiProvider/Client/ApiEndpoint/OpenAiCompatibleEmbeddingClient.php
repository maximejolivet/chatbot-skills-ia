<?php

namespace App\AiProvider\Client\ApiEndpoint;

use App\AiProvider\Client\EmbeddingClientInterface;
use App\AiProvider\Client\EmbeddingResult;
use App\AiProvider\Client\TokenEstimator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OpenAiCompatibleEmbeddingClient implements EmbeddingClientInterface
{
    public string $apiEndpoint;
    private HttpClientInterface $httpClient;

    public function __construct(
        string $apiEndpoint,
        public string $apiKey,
        public string $model,
        public int $timeout = 30,
        ?HttpClientInterface $httpClient = null,
    ) {
        if ('' === $apiKey) {
            throw new \InvalidArgumentException('API key not configured for this provider.');
        }
        $this->apiEndpoint = self::deriveEmbeddingEndpoint($apiEndpoint);
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * Heuristic: derive an /embeddings URL from a /chat/completions URL.
     */
    public static function deriveEmbeddingEndpoint(string $chatEndpoint): string
    {
        if (str_ends_with($chatEndpoint, '/chat/completions')) {
            return str_replace('/chat/completions', '/embeddings', $chatEndpoint);
        }
        if (str_ends_with($chatEndpoint, '/v1/chat/completions')) {
            return str_replace('/v1/chat/completions', '/v1/embeddings', $chatEndpoint);
        }
        if (str_ends_with($chatEndpoint, '/chat/completions/')) {
            return str_replace('/chat/completions/', '/embeddings', $chatEndpoint);
        }
        if (str_ends_with($chatEndpoint, '/embeddings')) {
            return $chatEndpoint;
        }

        return rtrim($chatEndpoint, '/') . '/embeddings';
    }

    public function embed(string $text): EmbeddingResult
    {
        $response = $this->httpClient->request('POST', $this->apiEndpoint, [
            'json' => ['input' => $text, 'model' => $this->model],
            'headers' => $this->headers(),
            'timeout' => $this->timeout,
        ]);
        $result = $response->toArray();
        $data = $result['data'] ?? [];
        if (!$data || !isset($data[0]['embedding'])) {
            throw new \RuntimeException('Unexpected embedding response format');
        }

        $usage = $result['usage'] ?? [];
        $promptTokens = $usage['prompt_tokens'] ?? null;
        $totalTokens = $usage['total_tokens'] ?? null;
        if (null === $promptTokens || null === $totalTokens) {
            $promptTokens = TokenEstimator::estimate($text);
            $totalTokens = $promptTokens;
            $source = 'estimated';
        } else {
            $source = 'provider';
        }

        return new EmbeddingResult(
            vector: $data[0]['embedding'],
            usage: [
                'prompt_tokens' => $promptTokens,
                'total_tokens' => $totalTokens,
                'source' => $source,
                'provider' => 'api_endpoint',
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
            $modelsEndpoint = str_replace('/embeddings', '/models', $this->apiEndpoint);
            $response = $this->httpClient->request('GET', $modelsEndpoint, ['headers' => $this->headers(), 'timeout' => 10]);
            if (200 === $response->getStatusCode()) {
                return [
                    'status' => 'ok',
                    'provider' => 'api_endpoint',
                    'api_endpoint' => $this->apiEndpoint,
                    'model' => $this->model,
                ];
            }

            return [
                'status' => 'error',
                'provider' => 'api_endpoint',
                'api_endpoint' => $this->apiEndpoint,
                'message' => sprintf('HTTP %d', $response->getStatusCode()),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'provider' => 'api_endpoint',
                'api_endpoint' => $this->apiEndpoint,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->apiKey];
    }
}
