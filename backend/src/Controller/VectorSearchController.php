<?php

namespace App\Controller;

use App\ApiResource\VectorSearchRequest;
use App\VectorConnector\VectorSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
final readonly class VectorSearchController
{
    public function __construct(
        private VectorSearchService $vectorSearchService,
        private ValidatorInterface $validator,
    ) {}

    // VectorSearchAction (not a Doctrine entity, no resource-level
    // `security:` to begin with) had no access control at all -- same class
    // of gap as the 9 custom controllers fixed earlier this session, found
    // in a follow-up audit pass. Triggers a real Qdrant query per call
    // (cost/DoS surface) and isn't in the Nuxt proxy's allowlist, so this is
    // defense-in-depth rather than closing a currently-reachable hole.
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $dto = new VectorSearchRequest();
        $dto->query = is_string($data['query'] ?? null) ? $data['query'] : '';
        $dto->collectionName = isset($data['collection_name']) && '' !== $data['collection_name']
            ? (string) $data['collection_name']
            : null;
        $dto->categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $dto->documentType = isset($data['document_type']) && '' !== $data['document_type'] ? (string) $data['document_type'] : null;
        $dto->language = isset($data['language']) && '' !== $data['language'] ? (string) $data['language'] : null;
        $dto->complexity = isset($data['complexity']) && '' !== $data['complexity'] ? (string) $data['complexity'] : null;
        $dto->limit = isset($data['limit']) ? (int) $data['limit'] : 10;

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], 400);
        }

        $filterConditions = array_filter([
            'category_id' => $dto->categoryId,
            'document_type' => $dto->documentType,
            'language' => $dto->language,
            'complexity' => $dto->complexity,
        ], static fn(mixed $v): bool => null !== $v);
        $filterConditions = $filterConditions ?: null;

        $results = $this->vectorSearchService->search(
            query: $dto->query,
            collectionName: $dto->collectionName,
            limit: $dto->limit,
            filterConditions: $filterConditions,
        );

        return new JsonResponse(['query' => $dto->query, 'results' => $results, 'total' => count($results)]);
    }
}
