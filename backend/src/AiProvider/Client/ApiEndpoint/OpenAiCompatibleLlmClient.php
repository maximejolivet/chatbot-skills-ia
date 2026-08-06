<?php

namespace App\AiProvider\Client\ApiEndpoint;

use App\AiProvider\Client\ChatMessage;
use App\AiProvider\Client\CompletionResult;
use App\AiProvider\Client\LlmClientInterface;
use App\AiProvider\Client\TokenEstimator;
use App\AiProvider\Client\ToolCall;
use App\AiProvider\Client\ToolSpec;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * LLM client for OpenAI-compatible API endpoints (Chat Completions format) --
 * the local alternative to Ollama for chat generation.
 */
final class OpenAiCompatibleLlmClient implements LlmClientInterface
{
    private HttpClientInterface $httpClient;

    public function __construct(
        public readonly string $apiEndpoint,
        public readonly string $apiKey,
        public readonly string $model,
        public readonly int $timeout = 30,
        ?HttpClientInterface $httpClient = null,
    ) {
        if ('' === $apiKey) {
            throw new \InvalidArgumentException('API key not configured for this provider.');
        }
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    public function complete(array $messages, ?array $tools = null, float $temperature = 0.7, int $maxTokens = 3000): CompletionResult
    {
        $payload = [
            'model' => $this->model,
            'messages' => self::toOpenAiMessages($messages),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];
        $toolSpecs = self::toToolSpecs($tools);
        if ($toolSpecs) {
            $payload['tools'] = $toolSpecs;
        }

        $response = $this->httpClient->request('POST', $this->apiEndpoint, [
            'json' => $payload,
            'headers' => $this->headers(),
            'timeout' => $this->timeout,
        ]);
        $result = $response->toArray();

        $choice = $result['choices'][0] ?? [];
        $messageData = $choice['message'] ?? [];
        $content = $messageData['content'] ?? '';

        $toolCalls = [];
        foreach ($messageData['tool_calls'] ?? [] as $tc) {
            $function = $tc['function'] ?? [];
            $arguments = $function['arguments'] ?? '{}';
            if (is_string($arguments)) {
                $decoded = json_decode($arguments, true);
                $arguments = \JSON_ERROR_NONE === json_last_error() && is_array($decoded) ? $decoded : [];
            }
            $toolCalls[] = new ToolCall(id: $tc['id'] ?? '', name: $function['name'] ?? '', arguments: $arguments);
        }

        $usage = $result['usage'] ?? [];
        $promptTokens = $usage['prompt_tokens'] ?? null;
        $completionTokens = $usage['completion_tokens'] ?? null;
        if (null === $promptTokens || null === $completionTokens) {
            $promptTokens = TokenEstimator::estimate(json_encode($messages) ?: '');
            $completionTokens = TokenEstimator::estimate($content);
            $source = 'estimated';
        } else {
            $source = 'provider';
        }

        return new CompletionResult(
            message: new ChatMessage(role: 'assistant', content: trim($content), toolCalls: $toolCalls),
            usage: [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
                'source' => $source,
                'provider' => 'api_endpoint',
                'model' => $this->model,
            ],
        );
    }

    public function stream(array $messages, float $temperature = 0.7, int $maxTokens = 3000): iterable
    {
        $response = $this->httpClient->request('POST', $this->apiEndpoint, [
            'json' => [
                'model' => $this->model,
                'messages' => self::toOpenAiMessages($messages),
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'stream' => true,
            ],
            'headers' => $this->headers(),
            'timeout' => $this->timeout,
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
                if (str_starts_with($line, 'data: ')) {
                    $line = substr($line, 6);
                }
                if ('[DONE]' === trim($line)) {
                    return;
                }
                $data = json_decode($line, true);
                if (!is_array($data)) {
                    continue;
                }
                $choices = $data['choices'] ?? [];
                if (!$choices) {
                    continue;
                }
                $delta = $choices[0]['delta'] ?? [];
                $content = $delta['content'] ?? '';
                if ('' !== $content) {
                    yield $content;
                }
                if (!empty($choices[0]['finish_reason'])) {
                    return;
                }
            }
        }
    }

    public function checkStatus(): array
    {
        try {
            $modelsUrl = str_contains($this->apiEndpoint, '/chat/completions')
                ? str_replace('/chat/completions', '/models', $this->apiEndpoint)
                : $this->apiEndpoint;

            $response = $this->httpClient->request('GET', $modelsUrl, ['headers' => $this->headers(), 'timeout' => 5]);
            $statusCode = $response->getStatusCode();
            if (in_array($statusCode, [200, 401, 403], true)) {
                return [
                    'status' => 'reachable',
                    'provider' => 'api_endpoint',
                    'api_endpoint' => $this->apiEndpoint,
                    'model' => $this->model,
                ];
            }

            return [
                'status' => 'error',
                'provider' => 'api_endpoint',
                'api_endpoint' => $this->apiEndpoint,
                'message' => sprintf('HTTP %d', $statusCode),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'not_reachable',
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
        return ['Content-Type' => 'application/json', 'Authorization' => 'Bearer '.$this->apiKey];
    }

    /**
     * @param ChatMessage[] $messages
     *
     * @return array<int, array<string, mixed>>
     */
    private static function toOpenAiMessages(array $messages): array
    {
        $result = [];
        foreach ($messages as $msg) {
            $entry = ['role' => $msg->role, 'content' => $msg->content];
            if ($msg->toolCalls) {
                $entry['tool_calls'] = array_map(
                    static fn (ToolCall $tc) => [
                        'id' => $tc->id,
                        'type' => 'function',
                        'function' => ['name' => $tc->name, 'arguments' => json_encode($tc->arguments ?: new \stdClass())],
                    ],
                    $msg->toolCalls,
                );
            }
            if ('tool' === $msg->role) {
                $entry['tool_call_id'] = $msg->toolCallId;
                $entry['name'] = $msg->name;
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
     * PHP array always serializes to `[]`, not `{}`, which providers reject as
     * an invalid tool schema.
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
