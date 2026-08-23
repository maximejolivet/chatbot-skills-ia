<?php

namespace App\VectorConnector;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Thin REST wrapper around Qdrant. The only place that talks to Qdrant --
 * knows nothing about `chat` or `knowledge_base`; collection names are
 * always passed in already resolved by the caller.
 */
final class QdrantClient
{
    public const int VECTOR_SIZE = 1024; // mxbai-embed-large embedding dimension

    /** @var array<string, true> */
    private array $ensuredCollections = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'QDRANT_HOST')]
        private readonly string $host,
        #[Autowire(env: 'QDRANT_PORT')]
        private readonly string $port,
        #[Autowire(env: 'QDRANT_API_KEY')]
        private readonly string $apiKey,
    ) {}

    /**
     * Cheap reachability probe for the aggregated health endpoint (see
     * App\Controller\HealthController) -- lists collections rather than
     * hitting Qdrant's bare root, so a reachable-but-misconfigured instance
     * (wrong API key, etc.) still surfaces as an error instead of a false OK.
     *
     * @return array{status: string, message?: string}
     */
    public function ping(): array
    {
        try {
            $response = $this->request('GET', '/collections', ['timeout' => 5]);
            if (200 === $response->getStatusCode()) {
                return ['status' => 'ok'];
            }

            return ['status' => 'error', 'message' => sprintf('HTTP %d', $response->getStatusCode())];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Idempotent, and cheap to call repeatedly within a request (cached per-instance).
     */
    public function ensureCollection(string $collectionName): void
    {
        if (isset($this->ensuredCollections[$collectionName])) {
            return;
        }

        try {
            $response = $this->request('GET', "/collections/{$collectionName}");
            if (404 === $response->getStatusCode()) {
                $this->request('PUT', "/collections/{$collectionName}", [
                    'json' => [
                        'vectors' => ['size' => self::VECTOR_SIZE, 'distance' => 'Cosine'],
                    ],
                ]);
                $this->logger->info('Created Qdrant collection: {collection}', ['collection' => $collectionName]);
            }
            $this->ensuredCollections[$collectionName] = true;
        } catch (\Throwable $e) {
            $this->logger->error('Error ensuring Qdrant collection {collection}: {error}', [
                'collection' => $collectionName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @param array<int, array{id: string, vector: float[], payload: array<string, mixed>}> $points
     */
    public function upsert(string $collectionName, array $points): void
    {
        $this->ensureCollection($collectionName);
        $this->request('PUT', "/collections/{$collectionName}/points", [
            'query' => ['wait' => 'true'],
            'json' => ['points' => $points],
        ]);
        $this->logger->info('Upserted {count} vectors into collection {collection}', [
            'count' => count($points),
            'collection' => $collectionName,
        ]);
    }

    /**
     * @param float[]                   $queryVector
     * @param array<string, mixed>|null $filterConditions
     *
     * @return array<int, array{id: string|int, score: float, payload: array<string, mixed>}>
     */
    public function search(string $collectionName, array $queryVector, int $limit = 10, ?array $filterConditions = null): array
    {
        $this->ensureCollection($collectionName);

        $payload = [
            'query' => $queryVector,
            'limit' => $limit,
            'with_payload' => true,
        ];
        if ($filterConditions) {
            $payload['filter'] = [
                'must' => array_map(
                    static fn(string $key, mixed $value): array => ['key' => $key, 'match' => ['value' => $value]],
                    array_keys($filterConditions),
                    array_values($filterConditions),
                ),
            ];
        }

        $response = $this->request('POST', "/collections/{$collectionName}/points/query", ['json' => $payload]);
        $points = $response->toArray()['result']['points'] ?? [];

        return array_map(
            static fn(array $point): array => [
                'id' => $point['id'],
                'score' => $point['score'],
                'payload' => $point['payload'] ?? [],
            ],
            $points,
        );
    }

    /**
     * Returns whether the delete actually succeeded.
     *
     * @param array<int, string> $pointIds
     */
    public function delete(string $collectionName, array $pointIds): bool
    {
        try {
            $this->request('POST', "/collections/{$collectionName}/points/delete", [
                'json' => ['points' => $pointIds],
            ]);
            $this->logger->info('Deleted {count} vectors from collection {collection}', [
                'count' => count($pointIds),
                'collection' => $collectionName,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Error deleting vectors from collection {collection}: {error}', [
                'collection' => $collectionName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        if ('' !== $this->apiKey) {
            $options['headers'] = ['api-key' => $this->apiKey];
        }

        // QDRANT_HOST is a bare hostname for local/self-hosted Qdrant (e.g. "qdrant" in
        // Docker), but Qdrant Cloud gives you a full "https://...qdrant.io" URL -- only
        // default to http:// when no scheme is already present, instead of always
        // prepending one and breaking cloud (TLS-only) instances.
        $base = str_contains($this->host, '://') ? $this->host : "http://{$this->host}";

        // getStatusCode() never throws, even on a 4xx/5xx response -- callers that need
        // the body (toArray()) on a successful request will still get the usual exception.
        return $this->httpClient->request($method, "{$base}:{$this->port}{$path}", $options);
    }
}
