<?php

declare(strict_types=1);

namespace App\VectorConnector;

final readonly class IndexingResult
{
    /**
     * @param array<int, string>        $chunkPointIds    chunk_index => Qdrant point id
     * @param array<string, mixed>|null $embeddingUsage
     * @param array<string, mixed>|null $analysisMetadata
     */
    public function __construct(
        public bool $success,
        public array $chunkPointIds = [],
        public ?array $embeddingUsage = null,
        public ?array $analysisMetadata = null,
        public ?string $error = null,
    ) {}
}
