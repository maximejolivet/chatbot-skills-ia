<?php

namespace App\Controller;

use App\AiProvider\ProviderSelectionService;
use App\VectorConnector\QdrantClient;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Aggregated health check (DB + Qdrant + Redis + Ollama/LLM provider) in one
 * call, for monitoring/supervision -- each of these already had its own way
 * to check (a DB query anywhere, `App\VectorConnector\QdrantClient::ping()`,
 * a raw Redis ping, `GET /api/chat/llm-status`), but nothing combined them.
 * Every check is independent and best-effort: one failing service never
 * throws, it just reports `status: "error"` for that entry -- a caller
 * scraping this endpoint should see a partial outage, not a 500.
 */
#[AsController]
final readonly class HealthController
{
    public function __construct(
        private Connection $connection,
        private QdrantClient $qdrantClient,
        private ProviderSelectionService $providerSelectionService,
        private LoggerInterface $logger,
        #[Autowire(env: 'REDIS_URL')]
        private string $redisUrl,
    ) {}

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'qdrant' => $this->qdrantClient->ping(),
            'redis' => $this->checkRedis(),
            'llm' => $this->checkLlm(),
        ];

        // 'ok': database/Qdrant/Redis. 'running'/'reachable': the two success
        // states across LlmClientInterface implementations (Ollama vs. an
        // OpenAI-compatible endpoint) -- see App\AiProvider\Client\*.
        $allOk = array_all($checks, static fn(array $check): bool => \in_array($check['status'], ['ok', 'running', 'reachable'], true));

        return new JsonResponse([
            'status' => $allOk ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $allOk ? 200 : 503);
    }

    /**
     * @return array{status: string, message?: string}
     */
    private function checkDatabase(): array
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            $this->logger->error('Health check: database unreachable: {error}', ['error' => $e->getMessage()]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, message?: string}
     */
    private function checkRedis(): array
    {
        try {
            $redis = RedisAdapter::createConnection($this->redisUrl, ['timeout' => 2]);
            if (false === $redis->ping()) {
                return ['status' => 'error', 'message' => 'PING failed'];
            }

            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            $this->logger->error('Health check: Redis unreachable: {error}', ['error' => $e->getMessage()]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkLlm(): array
    {
        try {
            return $this->providerSelectionService->checkLlmStatus();
        } catch (\Throwable $e) {
            $this->logger->error('Health check: LLM provider unreachable: {error}', ['error' => $e->getMessage()]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
