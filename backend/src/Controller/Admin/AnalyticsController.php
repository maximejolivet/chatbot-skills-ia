<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Chat\AnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Not a Sylius CRUD resource (nothing to create/edit/delete here, it's a
 * read-only aggregation) -- a plain controller + route, same pattern as
 * DashboardController. Route name follows the `app_admin_{resource}_*`
 * convention anyway (`_index`) so the existing sidebar highlighting in
 * admin/layout.html.twig (which matches on that prefix) picks it up for
 * free, and so it can be added to AdminExtension::nav() with the same
 * `$item()` helper as every Sylius resource.
 */
final class AnalyticsController extends AbstractController
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    #[Route('/admin/analytics', name: 'app_admin_analytics_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/analytics/index.html.twig', [
            'stats' => $this->analyticsService->getDashboardStats(),
        ]);
    }
}
