<?php

namespace App\Chat;

use App\AiProvider\Client\ChatMessage as LlmChatMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Caches the recent-history lookup ChatService needs on every chat turn
 * (MessageRepository::recentHistory()), keyed by conversation. Invalidated
 * by ChatService right after a turn persists its user+assistant messages, so
 * a cached entry is never allowed to go stale -- it either reflects the
 * conversation as of the last completed turn, or it's absent.
 *
 * TTL comes from the pool's own default_lifetime (config/packages/cache.yaml),
 * not set per-item here.
 */
final class ConversationHistoryCache
{
    public function __construct(
        #[Autowire(service: 'cache.conversation_history')]
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @param callable(): LlmChatMessage[] $callback
     *
     * @return LlmChatMessage[]
     */
    public function remember(int $conversationId, callable $callback): array
    {
        $rows = $this->cache->get(
            $this->key($conversationId),
            static fn () => array_map(
                static fn (LlmChatMessage $m) => ['role' => $m->role, 'content' => $m->content],
                $callback(),
            ),
        );

        return array_map(
            static fn (array $row) => new LlmChatMessage(role: $row['role'], content: $row['content']),
            $rows,
        );
    }

    public function invalidate(int $conversationId): void
    {
        $this->cache->delete($this->key($conversationId));
    }

    private function key(int $conversationId): string
    {
        return "conversation_history.{$conversationId}";
    }
}
