<?php

namespace App\Tests\VectorConnector;

use App\VectorConnector\QdrantClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class QdrantClientTest extends TestCase
{
    private function client(HttpClientInterface $httpClient): QdrantClient
    {
        return new QdrantClient($httpClient, $this->createStub(LoggerInterface::class), 'qdrant-host', '6333', '');
    }

    public function testPingReturnsOkOn200(): void
    {
        $http = new MockHttpClient(fn(): MockResponse => new MockResponse('{}', ['http_code' => 200]));

        self::assertSame(['status' => 'ok'], $this->client($http)->ping());
    }

    public function testPingReturnsErrorOnNon200(): void
    {
        $http = new MockHttpClient(fn(): MockResponse => new MockResponse('', ['http_code' => 503]));

        self::assertSame(['status' => 'error', 'message' => 'HTTP 503'], $this->client($http)->ping());
    }

    public function testPingReturnsErrorOnTransportFailure(): void
    {
        $http = new MockHttpClient(fn() => throw new \RuntimeException('connection refused'));

        $result = $this->client($http)->ping();

        self::assertSame('error', $result['status']);
        self::assertArrayHasKey('message', $result);
        self::assertStringContainsString('connection refused', $result['message'] ?? '');
    }

    public function testSearchParsesPointsFromResponse(): void
    {
        $collectionExists = new MockResponse('{}', ['http_code' => 200]);
        $searchResponse = new MockResponse(json_encode([
            'result' => ['points' => [
                ['id' => 'abc', 'score' => 0.91, 'payload' => ['content' => 'hello']],
            ]],
        ]) ?: '{}', ['http_code' => 200]);
        $http = new MockHttpClient([$collectionExists, $searchResponse]);

        $results = $this->client($http)->search('my_collection', [0.1, 0.2], 5);

        self::assertSame([['id' => 'abc', 'score' => 0.91, 'payload' => ['content' => 'hello']]], $results);
    }

    public function testDeleteReturnsFalseInsteadOfThrowingOnFailure(): void
    {
        $http = new MockHttpClient(fn() => throw new \RuntimeException('down'));

        self::assertFalse($this->client($http)->delete('my_collection', ['point-1']));
    }

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $http = new MockHttpClient(fn(): MockResponse => new MockResponse('{}', ['http_code' => 200]));

        self::assertTrue($this->client($http)->delete('my_collection', ['point-1']));
    }

    public function testEnsureCollectionCreatesWhenMissing(): void
    {
        $requests = [];
        $http = new MockHttpClient(function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $method;

            return '/collections/new_collection' === parse_url($url, \PHP_URL_PATH) && 'GET' === $method
                ? new MockResponse('', ['http_code' => 404])
                : new MockResponse('{}', ['http_code' => 200]);
        });

        $this->client($http)->ensureCollection('new_collection');

        self::assertSame(['GET', 'PUT'], $requests);
    }

    public function testEnsureCollectionSkipsCreationWhenAlreadyExists(): void
    {
        $requests = [];
        $http = new MockHttpClient(function (string $method) use (&$requests): MockResponse {
            $requests[] = $method;

            return new MockResponse('{}', ['http_code' => 200]);
        });

        $this->client($http)->ensureCollection('existing_collection');

        self::assertSame(['GET'], $requests);
    }
}
