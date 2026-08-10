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

    /**
     * Equality filters on the intelligent-analysis payload fields
     * (App\VectorConnector\DocumentAnalysisService) -- document_type
     * (e.g. "rapport", "manuel"), language (ISO-ish code, e.g. "fr"),
     * complexity ("débutant"/"intermédiaire"/"avancé"/"expert").
     */
    #[Assert\Length(max: 100)]
    public ?string $documentType = null;

    #[Assert\Length(max: 10)]
    public ?string $language = null;

    #[Assert\Length(max: 50)]
    public ?string $complexity = null;

    #[Assert\Range(min: 1, max: 50)]
    public int $limit = 10;
}
