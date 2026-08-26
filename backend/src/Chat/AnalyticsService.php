<?php

declare(strict_types=1);

namespace App\Chat;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\SearchQuery;
use App\Enum\MessageFeedback;
use App\Enum\MessageRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Read-only aggregation over data that already exists (Message.feedback,
 * Message.metadata.token_usage, SearchQuery) but had no aggregated view --
 * backs the /admin/analytics dashboard. Cached (dedicated Redis pool, 5 min
 * TTL, config/packages/cache.yaml) -- revisits an earlier "no caching
 * needed, cheap COUNT/AVG queries" call: tokenUsageStats() in particular
 * scans every assistant message's metadata JSON in PHP (see that method),
 * not a bounded aggregate, and only gets heavier as message volume grows.
 * No per-viewer variation (every admin sees the same numbers), so a single
 * static cache key is correct.
 */
final readonly class AnalyticsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'cache.admin_analytics')]
        private CacheInterface $cache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getDashboardStats(): array
    {
        return $this->cache->get('dashboard_stats', fn(): array => [
            'conversations' => $this->conversationStats(),
            'messages' => $this->messageStats(),
            'feedback' => $this->feedbackStats(),
            'token_usage' => $this->tokenUsageStats(),
            'search_queries' => $this->searchQueryStats(),
        ]);
    }

    /**
     * @return array{total: int, active: int}
     */
    private function conversationStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $total = (int) $qb->select('COUNT(c.id)')->from(Conversation::class, 'c')->getQuery()->getSingleScalarResult();

        $qb = $this->entityManager->createQueryBuilder();
        $active = (int) $qb->select('COUNT(c.id)')->from(Conversation::class, 'c')
            ->andWhere('c.isActive = true')
            ->getQuery()->getSingleScalarResult();

        return ['total' => $total, 'active' => $active];
    }

    /**
     * @return array{total: int, by_role: array<string, int>}
     */
    private function messageStats(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('m.role AS role', 'COUNT(m.id) AS c')
            ->from(Message::class, 'm')
            ->groupBy('m.role')
            ->getQuery()
            ->getResult();

        $byRole = [];
        $total = 0;
        foreach ($rows as $row) {
            // Doctrine hydrates the enum-typed group key back to a
            // MessageRole instance even in a scalar select; ->value keeps
            // the array plain-string-keyed for the Twig template.
            $byRole[$row['role']->value] = (int) $row['c'];
            $total += (int) $row['c'];
        }

        return ['total' => $total, 'by_role' => $byRole];
    }

    /**
     * @return array{positive: int, negative: int, none: int}
     */
    private function feedbackStats(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('m.feedback AS feedback', 'COUNT(m.id) AS c')
            ->from(Message::class, 'm')
            ->andWhere('m.role = :role')
            ->setParameter('role', MessageRole::Assistant)
            ->groupBy('m.feedback')
            ->getQuery()
            ->getResult();

        $stats = ['positive' => 0, 'negative' => 0, 'none' => 0];
        foreach ($rows as $row) {
            // PHPStan's Doctrine scalar-select type inference doesn't reflect
            // that m.feedback is nullable here -- MessageFeedback::feedback
            // really can be null (see its docblock), so this stays an
            // explicit instanceof check rather than trusting that inference.
            $key = $row['feedback'] instanceof MessageFeedback ? $row['feedback']->value : 'none';
            $stats[$key] = (int) $row['c'];
        }

        return $stats;
    }

    /**
     * total_tokens lives inside the `metadata` JSON column
     * (metadata.token_usage.total_tokens, see MessageSerializer) -- summed
     * in PHP rather than a native JSON_EXTRACT aggregate to stay portable
     * across the JSON functions actually available, and because message
     * volume on this app is small enough that loading assistant messages'
     * metadata is cheap.
     *
     * @return array{total_tokens: int, message_count: int}
     */
    private function tokenUsageStats(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('m.metadata AS metadata')
            ->from(Message::class, 'm')
            ->andWhere('m.role = :role')
            ->setParameter('role', MessageRole::Assistant)
            ->getQuery()
            ->getResult();

        $totalTokens = 0;
        $withUsage = 0;
        foreach ($rows as $row) {
            $tokens = $row['metadata']['token_usage']['total_tokens'] ?? null;
            if (null !== $tokens) {
                $totalTokens += (int) $tokens;
                ++$withUsage;
            }
        }

        return ['total_tokens' => $totalTokens, 'message_count' => $withUsage];
    }

    /**
     * @return array{total: int, avg_execution_time: float, avg_results_count: float, recent: SearchQuery[]}
     */
    private function searchQueryStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $aggregate = $qb->select('COUNT(s.id) AS total', 'AVG(s.executionTime) AS avg_time', 'AVG(s.resultsCount) AS avg_results')
            ->from(SearchQuery::class, 's')
            ->getQuery()
            ->getSingleResult();
        if (!\is_array($aggregate)) {
            throw new \LogicException('Expected an aggregate result row.');
        }

        $recent = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(SearchQuery::class, 's')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return [
            'total' => \is_numeric($aggregate['total'] ?? null) ? (int) $aggregate['total'] : 0,
            'avg_execution_time' => \is_numeric($aggregate['avg_time'] ?? null) ? (float) $aggregate['avg_time'] : 0.0,
            'avg_results_count' => \is_numeric($aggregate['avg_results'] ?? null) ? (float) $aggregate['avg_results'] : 0.0,
            'recent' => $recent,
        ];
    }
}
