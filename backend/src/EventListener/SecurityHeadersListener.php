<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Security headers (CSP, HSTS, etc.) on every /admin response -- the
 * backoffice, not the /api firewall (a JSON API consumed by the Nuxt proxy
 * and curl/scripts, not a browser rendering untrusted third-party content
 * the way a page navigation is). Same rationale and header set as the
 * frontend widget's (frontend/nuxt.config.ts routeRules), adapted to what
 * this backoffice actually loads: everything is self-hosted via AssetMapper
 * (assets/app.js -- Stimulus controllers, compiled Tailwind CSS), no Google
 * Fonts or any other external origin here, so font-src/img-src stay 'self'
 * (+ data: for inline SVG/icons). 'unsafe-inline' on script-src covers the
 * AssetMapper importmap() Twig call, which renders an inline
 * `<script type="importmap">` block; on style-src it covers the one
 * dynamic inline `style="width: …%"` in templates/admin/analytics/index.html.twig
 * (a feedback bar, computed server-side per request -- not worth a CSS
 * custom-property refactor just to drop one directive). No dev/prod split
 * needed here unlike the frontend's (no HMR websocket to allow for a
 * server-rendered Twig app).
 */
final class SecurityHeadersListener implements EventSubscriberInterface
{
    private const CSP = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "font-src 'self'; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; "
        . "frame-ancestors 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'";

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !str_starts_with($event->getRequest()->getPathInfo(), '/admin')) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Harmless to always send: browsers only act on it over an actual
        // HTTPS connection, so this is inert on plain-HTTP local dev.
        $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $headers->set('Content-Security-Policy', self::CSP);
    }
}
