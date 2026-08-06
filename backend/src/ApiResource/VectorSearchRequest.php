<?php

namespace App\ApiResource;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The one canonical vector search request shape (mirrors
 * vector_connector.serializers.VectorSearchRequestSerializer).
 */
final class VectorSearchRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    public string $query = '';

    #[Assert\Length(max: 100)]
    public ?string $collectionName = null;

    public ?int $categoryId = null;

    #[Assert\Range(min: 1, max: 50)]
    public int $limit = 10;
}
