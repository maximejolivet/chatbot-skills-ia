<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Not a Sylius CRUD resource (append-only, nothing to create/edit/delete
 * through this page) -- a plain controller + route, same pattern as
 * AnalyticsController/DashboardController.
 */
final class AuditLogController extends AbstractController
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/admin/audit-log', name: 'app_admin_audit_log_index', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        $query = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->orderBy('a.occurredAt', 'DESC')
            ->setFirstResult((int) (($page - 1) * self::PAGE_SIZE))
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery();

        $paginator = new Paginator($query);
        $nbResults = \count($paginator);
        $nbPages = (int) ceil($nbResults / self::PAGE_SIZE);

        return $this->render('admin/audit_log/index.html.twig', [
            'entries' => $paginator,
            'currentPage' => $page,
            'nbPages' => $nbPages,
            'nbResults' => $nbResults,
            'hasPreviousPage' => $page > 1,
            'hasNextPage' => $page < $nbPages,
        ]);
    }
}
