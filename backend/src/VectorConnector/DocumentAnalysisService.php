<?php

namespace App\VectorConnector;

use App\AiProvider\Client\ChatMessage;
use App\AiProvider\ProviderSelectionService;
use Psr\Log\LoggerInterface;

/**
 * Extracts structured metadata from a document using the dedicated Ollama analysis
 * model (OLLAMA_ANALYSIS_MODEL), through the shared ai_providers LLM client transport.
 */
final readonly class DocumentAnalysisService
{
    /**
     * @var array<string, mixed>
     */
    public const array DEFAULT_DOCUMENT_METADATA = [
        'document_type' => 'document',
        'category' => 'général',
        'language' => 'fr',
        'summary' => '',
        'keywords' => [],
        'topics' => [],
        'complexity' => 'intermédiaire',
        'target_audience' => 'général',
        'relevance_score' => 5,
        'technical_terms' => [],
        'entities' => ['organizations' => [], 'people' => [], 'locations' => [], 'dates' => []],
        'sentiment' => 'neutre',
        'confidence' => 1,
    ];

    public function __construct(
        private ProviderSelectionService $providerSelectionService,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyzeDocument(string $content, string $filename = ''): array
    {
        try {
            $prompt = $this->buildAnalysisPrompt($content, $filename);
            $result = $this->providerSelectionService->getAnalysisLlmClient()->complete(
                messages: [new ChatMessage(role: 'user', content: $prompt)],
                temperature: 0.3,
                maxTokens: 2000,
            );

            return $this->parseAnalysisResponse($result->message->content);
        } catch (\Throwable $e) {
            $this->logger->error('Error during document analysis: {error}', ['error' => $e->getMessage()]);

            return self::DEFAULT_DOCUMENT_METADATA;
        }
    }

    private function buildAnalysisPrompt(string $content, string $filename): string
    {
        $contentPreview = mb_strlen($content) > 8000 ? mb_substr($content, 0, 8000) . '...' : $content;

        return <<<PROMPT
            Tu es un expert en analyse de documents. Analyse le contenu suivant et extrais des métadonnées intelligentes.

            FICHIER: {$filename}
            CONTENU:
            {$contentPreview}

            Analyse ce document et fournis une réponse JSON avec les métadonnées suivantes:

            {
                "document_type": "Type de document (ex: rapport, manuel, procédure, documentation technique, article, etc.)",
                "category": "Catégorie principale (ex: technique, administratif, médical, juridique, etc.)",
                "language": "Langue détectée (fr, en, es, etc.)",
                "summary": "Résumé concis du document en 2-3 phrases",
                "keywords": ["mot-clé1", "mot-clé2", "mot-clé3", "mot-clé4", "mot-clé5"],
                "topics": ["sujet1", "sujet2", "sujet3"],
                "complexity": "Niveau de complexité (débutant, intermédiaire, avancé, expert)",
                "target_audience": "Public cible (général, professionnel, technique, spécialisé, etc.)",
                "relevance_score": "Score de pertinence de 1 à 10",
                "technical_terms": ["terme1", "terme2", "terme3"],
                "entities": {
                    "organizations": ["org1", "org2"],
                    "people": ["personne1", "personne2"],
                    "locations": ["lieu1", "lieu2"],
                    "dates": ["date1", "date2"]
                },
                "sentiment": "Sentiment général (positif, neutre, négatif)",
                "confidence": "Niveau de confiance de l'analyse (1-10)"
            }

            Réponds UNIQUEMENT avec le JSON, sans texte supplémentaire.
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAnalysisResponse(string $text): array
    {
        $jsonStart = strpos($text, '{');
        $jsonEnd = strrpos($text, '}');
        if (false === $jsonStart || false === $jsonEnd || $jsonEnd <= $jsonStart) {
            $this->logger->warning('Could not extract JSON from analysis response');

            return self::DEFAULT_DOCUMENT_METADATA;
        }

        $decoded = json_decode(substr($text, $jsonStart, $jsonEnd - $jsonStart + 1), true);
        if (\JSON_ERROR_NONE !== json_last_error() || !is_array($decoded)) {
            $this->logger->error('JSON parsing error in analysis response');

            return self::DEFAULT_DOCUMENT_METADATA;
        }

        $metadata = [];
        foreach ($decoded as $key => $value) {
            if (\is_string($key)) {
                $metadata[$key] = $value;
            }
        }

        return $this->validateMetadata($metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function validateMetadata(array $metadata): array
    {
        $clampScore = static fn(mixed $value): int => min(max(\is_numeric($value) ? (int) $value : 5, 1), 10);

        $keywords = \is_array($metadata['keywords'] ?? null) ? array_slice($metadata['keywords'], 0, 10) : [];
        $topics = \is_array($metadata['topics'] ?? null) ? array_slice($metadata['topics'], 0, 5) : [];
        $technicalTerms = \is_array($metadata['technical_terms'] ?? null) ? array_slice($metadata['technical_terms'], 0, 10) : [];
        $entities = \is_array($metadata['entities'] ?? null) ? $metadata['entities'] : self::DEFAULT_DOCUMENT_METADATA['entities'];

        return [
            'document_type' => $metadata['document_type'] ?? 'document',
            'category' => $metadata['category'] ?? 'général',
            'language' => $metadata['language'] ?? 'fr',
            'summary' => $metadata['summary'] ?? '',
            'keywords' => $keywords,
            'topics' => $topics,
            'complexity' => $metadata['complexity'] ?? 'intermédiaire',
            'target_audience' => $metadata['target_audience'] ?? 'général',
            'relevance_score' => $clampScore($metadata['relevance_score'] ?? null),
            'technical_terms' => $technicalTerms,
            'entities' => $entities,
            'sentiment' => $metadata['sentiment'] ?? 'neutre',
            'confidence' => $clampScore($metadata['confidence'] ?? null),
        ];
    }
}
