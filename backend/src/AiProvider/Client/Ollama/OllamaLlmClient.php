<?php

namespace App\AiProvider\Client\Ollama;

use App\AiProvider\Client\ChatMessage;
use App\AiProvider\Client\CompletionResult;
use App\AiProvider\Client\LlmClientInterface;
use App\AiProvider\Client\TokenEstimator;
use App\AiProvider\Client\ToolCall;
use App\AiProvider\Client\ToolSpec;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Uses Ollama's /api/chat endpoint (rather than /api/generate) -- /api/chat is what
 * supports the `tools` parameter and returns `message.tool_calls`, which real
 * tool-calling requires.
 */
final class OllamaLlmClient implements LlmClientInterface
{
    private HttpClientInterface $httpClient;

    public function __construct(
        public readonly string $baseUrl,
        public readonly string $model,
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    public function complete(array $messages, ?array $tools = null, float $temperature = 0.7, int $maxTokens = 3000): CompletionResult
    {
        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/api/chat', [
            'json' => [
                'model' => $this->model,
                'messages' => self::toOllamaMessages($messages),
                'tools' => self::toToolSpecs($tools),
                'stream' => false,
                'options' => [
                    'temperature' => $temperature,
                    'top_p' => 0.9,
                    'num_predict' => $maxTokens,
                ],
            ],
        ]);

        $data = $response->toArray();
        $message = $data['message'] ?? [];

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $tc) {
            $function = $tc['function'] ?? [];
            $toolCalls[] = new ToolCall(
                id: 'call_'.substr(bin2hex(random_bytes(4)), 0, 8),
                name: $function['name'] ?? '',
                arguments: $function['arguments'] ?? [],
            );
        }

        $content = $message['content'] ?? '';
        $promptTokens = $data['prompt_eval_count'] ?? null;
        $completionTokens = $data['eval_count'] ?? null;
        if (null === $promptTokens || null === $completionTokens) {
            $promptTokens = TokenEstimator::estimate(json_encode($messages) ?: '');
            $completionTokens = TokenEstimator::estimate($content);
            $source = 'estimated';
        } else {
            $source = 'provider';
        }

        return new CompletionResult(
            message: new ChatMessage(role: 'assistant', content: $content, toolCalls: $toolCalls),
            usage: [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
                'source' => $source,
                'provider' => 'ollama',
                'model' => $this->model,
            ],
        );
    }

    public function stream(array $messages, float $temperature = 0.7, int $maxTokens = 3000): iterable
    {
        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/api/chat', [
            'json' => [
                'model' => $this->model,
                'messages' => self::toOllamaMessages($messages),
                'stream' => true,
                'options' => [
                    'temperature' => $temperature,
                    'top_p' => 0.9,
                    'num_predict' => $maxTokens,
                ],
            ],
        ]);

        $buffer = '';
        foreach ($this->httpClient->stream($response) as $chunk) {
            $buffer .= $chunk->getContent();
            while (false !== ($pos = strpos($buffer, "\n"))) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ('' === $line) {
                    continue;
                }
                $data = json_decode($line, true);
                $content = $data['message']['content'] ?? '';
                if ('' !== $content) {
                    yield $content;
                }
            }
        }
    }

    public function checkStatus(): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/').'/api/tags', ['timeout' => 5]);
            if (200 === $response->getStatusCode()) {
                $models = $response->toArray()['models'] ?? [];
                $modelNames = array_map(static fn (array $m) => $m['name'], $models);
                $modelAvailable = array_any($modelNames, fn (string $name) => str_starts_with($name, $this->model));

                return [
                    'status' => 'running',
                    'model_available' => $modelAvailable,
                    'models' => $modelNames,
                    'base_url' => $this->baseUrl,
                    'model' => $this->model,
                ];
            }

            return ['status' => 'error', 'message' => sprintf('HTTP %d', $response->getStatusCode())];
        } catch (TransportException $e) {
            return ['status' => 'not_running', 'message' => "Ollama n'est pas démarré"];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @param ChatMessage[] $messages
     *
     * @return array<int, array<string, mixed>>
     */
    private static function toOllamaMessages(array $messages): array
    {
        $result = [];
        foreach ($messages as $msg) {
            $entry = ['role' => $msg->role, 'content' => $msg->content];
            if ($msg->toolCalls) {
                $entry['tool_calls'] = array_map(
                    static fn (ToolCall $tc) => ['function' => ['name' => $tc->name, 'arguments' => $tc->arguments ?: new \stdClass()]],
                    $msg->toolCalls,
                );
            }
            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @param ToolSpec[]|null $tools
     *
     * @return array<int, array<string, mixed>>|null
     */
    private static function toToolSpecs(?array $tools): ?array
    {
        if (!$tools) {
            return null;
        }

        return array_map(
            static fn (ToolSpec $tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => self::normalizeJsonSchema($tool->parameters),
                ],
            ],
            $tools,
        );
    }

    /**
     * A JSON Schema's "properties" must be a JSON object, but PHP's json_encode()
     * has no way to tell an empty array apart from an empty object -- an empty
     * PHP array always serializes to `[]`, not `{}`, which providers (Ollama
     * included) reject as an invalid tool schema.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private static function normalizeJsonSchema(array $schema): array
    {
        if (isset($schema['properties']) && [] === $schema['properties']) {
            $schema['properties'] = new \stdClass();
        }

        return $schema;
    }
}
