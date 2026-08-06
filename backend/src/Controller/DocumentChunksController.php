<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\DocumentChunk;
use App\Repository\DocumentChunkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;

#[AsController]
final class DocumentChunksController
{
    public function __construct(private readonly DocumentChunkRepository $chunkRepository)
    {
    }

    public function __invoke(Document $data): JsonResponse
    {
        $chunks = $this->chunkRepository->findForDocument($data->getId());

        return new JsonResponse(array_map($this->serialize(...), $chunks));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DocumentChunk $chunk): array
    {
        return [
            'id' => $chunk->getId(),
            'content' => $chunk->getContent(),
            'chunk_index' => $chunk->getChunkIndex(),
            'start_position' => $chunk->getStartPosition(),
            'end_position' => $chunk->getEndPosition(),
            'vector_id' => $chunk->getVectorId(),
            'is_vectorized' => $chunk->isVectorized(),
            'metadata' => $chunk->getMetadata(),
            'created_at' => $chunk->getCreatedAt()->format(\DATE_ATOM),
        ];
    }
}
