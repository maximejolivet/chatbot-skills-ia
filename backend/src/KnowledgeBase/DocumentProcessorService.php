<?php

namespace App\KnowledgeBase;

use App\Entity\Document;
use App\Enum\DocumentFileType;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Extracts text from a document and splits it into overlapping chunks.
 */
final class DocumentProcessorService
{
    private const int CHUNK_SIZE = 1000; // characters
    private const int CHUNK_OVERLAP = 200; // characters

    /**
     * Supports two sources: a physical uploaded file, or (as a fallback,
     * useful for quick text-only entries/tests) $document->getDescription().
     *
     * @return array<int, array{content: string, start_position: int, end_position: int, metadata: array<string, mixed>}>
     */
    public function processDocument(Document $document, ?string $absoluteFilePath): array
    {
        if ($absoluteFilePath && is_file($absoluteFilePath)) {
            $text = match ($document->getFileType()) {
                DocumentFileType::Pdf => $this->extractPdfText($absoluteFilePath),
                DocumentFileType::Txt, DocumentFileType::Md => $this->extractTxtText($absoluteFilePath),
                DocumentFileType::Docx => $this->extractDocxText($absoluteFilePath),
                DocumentFileType::Html => $this->extractHtmlText($absoluteFilePath),
                DocumentFileType::Json => $this->extractJsonText($absoluteFilePath),
            };
        } elseif ('' !== trim($document->getDescription())) {
            $text = $document->getDescription();
        } else {
            throw new \RuntimeException('The document has no associated file or textual content to process.');
        }

        return $this->createChunks($this->cleanText($text), $document);
    }

    private function extractPdfText(string $filePath): string
    {
        try {
            return new PdfParser()->parseFile($filePath)->getText();
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error extracting PDF text: {$e->getMessage()}", $e->getCode(), previous: $e);
        }
    }

    private function extractTxtText(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if (false === $content) {
            throw new \RuntimeException("Error reading text file: {$filePath}");
        }

        return $content;
    }

    private function extractDocxText(string $filePath): string
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($filePath)) {
            throw new \RuntimeException('Error extracting DOCX text: could not open archive');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (false === $xml) {
            throw new \RuntimeException('Error extracting DOCX text: word/document.xml not found');
        }

        // Turn paragraph boundaries into newlines before stripping tags, so the
        // extracted text keeps some structure instead of becoming one long line.
        $xml = str_replace(['</w:p>', '<w:br/>', '<w:br />'], "</w:p>\n", $xml);
        $text = html_entity_decode(strip_tags($xml), \ENT_QUOTES | \ENT_XML1, 'UTF-8');

        return trim($text) . "\n";
    }

    private function extractHtmlText(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if (false === $content) {
            throw new \RuntimeException("Error reading HTML file: {$filePath}");
        }

        return strip_tags($content);
    }

    private function extractJsonText(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if (false === $content) {
            throw new \RuntimeException("Error reading JSON file: {$filePath}");
        }

        $decoded = json_decode($content, true);
        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException('Error extracting JSON text: ' . json_last_error_msg());
        }

        return json_encode($decoded, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/(*UTF8)(*UCP)\s+/u', ' ', $text) ?? $text;
        $text = preg_replace('/(*UTF8)(*UCP)[^\w\s.,!?;:\-()]/u', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<int, array{content: string, start_position: int, end_position: int, metadata: array<string, mixed>}>
     */
    private function createChunks(string $text, Document $document): array
    {
        $chunks = [];
        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $end = $start + self::CHUNK_SIZE;
            if ($end < $length) {
                $searchStart = max($start + self::CHUNK_SIZE - self::CHUNK_OVERLAP, $start);
                $window = mb_substr($text, $searchStart, $end - $searchStart);
                $lastDot = mb_strrpos($window, '.');
                if (false !== $lastDot) {
                    $end = $searchStart + $lastDot + 1;
                }
            }

            $chunkText = trim(mb_substr($text, $start, $end - $start));
            if ('' !== $chunkText) {
                $chunks[] = [
                    'content' => $chunkText,
                    'start_position' => $start,
                    'end_position' => $end,
                    'metadata' => [
                        'document_id' => $document->getId(),
                        'document_title' => $document->getTitle(),
                        'document_type' => $document->getFileType()->value,
                        'category_id' => $document->getCategory()?->getId(),
                        'chunk_size' => mb_strlen($chunkText),
                    ],
                ];
            }

            $start = $end - self::CHUNK_OVERLAP;
            if ($start >= $length) {
                break;
            }
        }

        return $chunks;
    }
}
