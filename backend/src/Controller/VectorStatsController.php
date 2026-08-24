<?php

namespace App\Controller;

use App\Entity\SearchQuery;
use App\Repository\SearchQueryRepository;
use App\Repository\VectorIndexRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
final readonly class VectorStatsController
{
    public function __construct(
        private VectorIndexRepository $vectorIndexRepository,
        private SearchQueryRepository $searchQueryRepository,
    ) {}

    // See VectorSearchController's comment -- same gap, same fix, also
    // defense-in-depth (not in the Nuxt proxy's allowlist).
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'indexes_count' => count($this->vectorIndexRepository->findActive()),
            'total_queries' => $this->searchQueryRepository->count([]),
            'recent_queries' => array_map(
                $this->serializeSearchQuery(...),
                $this->searchQueryRepository->findRecent(10),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSearchQuery(SearchQuery $searchQuery): array
    {
        $vectorIndex = $searchQuery->getVectorIndex();

        return [
            'id' => $searchQuery->getId(),
            'query' => $searchQuery->getQuery(),
            'vector_index' => [
                'id' => $vectorIndex->getId(),
                'name' => $vectorIndex->getName(),
                'description' => $vectorIndex->getDescription(),
                'collection_id' => $vectorIndex->getCollectionId(),
                'dimension' => $vectorIndex->getDimension(),
                'created_at' => $vectorIndex->getCreatedAt()->format(\DATE_ATOM),
                'is_active' => $vectorIndex->isActive(),
                'metadata' => $vectorIndex->getMetadata(),
            ],
            'results_count' => $searchQuery->getResultsCount(),
            'execution_time' => $searchQuery->getExecutionTime(),
            'created_at' => $searchQuery->getCreatedAt()->format(\DATE_ATOM),
            'metadata' => $searchQuery->getMetadata(),
        ];
    }
}
