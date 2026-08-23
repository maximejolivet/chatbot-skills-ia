<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\SecurityHeadersListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersListenerTest extends TestCase
{
    private function respond(string $path, bool $mainRequest = true): Response
    {
        $response = new Response();
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($path),
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
            $response,
        );

        (new SecurityHeadersListener())->onKernelResponse($event);

        return $response;
    }

    public function testSetsSecurityHeadersOnAdminResponses(): void
    {
        $response = $this->respond('/admin/login');

        self::assertTrue($response->headers->has('Content-Security-Policy'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        self::assertStringContainsString('max-age=31536000', (string) $response->headers->get('Strict-Transport-Security'));
    }

    public function testLeavesNonAdminResponsesAlone(): void
    {
        $response = $this->respond('/api/ai_agents');

        self::assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function testLeavesSubRequestsAlone(): void
    {
        $response = $this->respond('/admin/login', mainRequest: false);

        self::assertFalse($response->headers->has('Content-Security-Policy'));
    }
}
