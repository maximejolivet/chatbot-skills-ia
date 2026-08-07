<?php

namespace App\AiProvider\Client;

use Psr\Log\LoggerInterface;

/**
 * Tries an ordered list of LlmClientInterface in turn, falling through to the
 * next on failure. The order is the AiProviderConfig priority chain (isDefault
 * DESC, updatedAt DESC) resolved by ProviderSelectionService -- with a single
 * client, this behaves exactly like calling that client directly.
 */
final readonly class FallbackLlmClient implements LlmClientInterface
{
    /**
     * @param LlmClientInterface[] $clients non-empty, primary first
     */
    public function __construct(
        private array $clients,
        private LoggerInterface $logger,
    ) {
        if (!$clients) {
            throw new \InvalidArgumentException('FallbackLlmClient needs at least one client.');
        }
    }

    public function complete(array $messages, ?array $tools = null, float $temperature = 0.7, int $maxTokens = 3000): CompletionResult
    {
        $lastException = null;
        foreach ($this->clients as $i => $client) {
            try {
                return $client->complete($messages, $tools, $temperature, $maxTokens);
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->logger->warning('LLM provider #{index} ({class}) failed, trying next: {error}', [
                    'index' => $i,
                    'class' => $client::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw $lastException;
    }

    /**
     * Fallback only covers picking which client to start streaming from --
     * once a client has yielded at least one chunk, a mid-stream failure
     * propagates as-is rather than restarting with the next client, which
     * would duplicate output the caller already received.
     */
    public function stream(array $messages, float $temperature = 0.7, int $maxTokens = 3000): iterable
    {
        $lastException = null;
        foreach ($this->clients as $i => $client) {
            try {
                $started = false;
                foreach ($client->stream($messages, $temperature, $maxTokens) as $chunk) {
                    $started = true;
                    yield $chunk;
                }

                return;
            } catch (\Throwable $e) {
                if ($started) {
                    throw $e;
                }
                $lastException = $e;
                $this->logger->warning('LLM provider #{index} ({class}) failed before yielding, trying next: {error}', [
                    'index' => $i,
                    'class' => $client::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw $lastException;
    }

    public function checkStatus(): array
    {
        $results = [];
        foreach ($this->clients as $client) {
            $status = $client->checkStatus();
            $results[] = $status;
            if ('reachable' === $status['status']) {
                return $status + ['fallback_checked' => count($results) - 1];
            }
        }

        return end($results) + ['fallback_checked' => count($results) - 1, 'all_results' => $results];
    }
}
