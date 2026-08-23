<?php

namespace App\Tests\KnowledgeBase;

use App\Entity\Document;
use App\Entity\DocumentCategory;
use App\Enum\DocumentFileType;
use App\KnowledgeBase\DocumentProcessorService;
use PHPUnit\Framework\TestCase;

final class DocumentProcessorServiceTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }
        $this->tempFiles = [];
    }

    private function tempFile(string $suffix, string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'doc_processor_test_') . $suffix;
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function document(DocumentFileType $type, string $description = ''): Document
    {
        $category = new DocumentCategory()->setName('cat')->setDescription('');

        return new Document()
            ->setTitle('Test document')
            ->setDescription($description)
            ->setFileType($type)
            ->setCategory($category);
    }

    public function testFallsBackToDescriptionWhenNoFileGiven(): void
    {
        $document = $this->document(DocumentFileType::Txt, 'A short description used as content.');

        $chunks = new DocumentProcessorService()->processDocument($document, null);

        self::assertCount(1, $chunks);
        self::assertSame('A short description used as content.', $chunks[0]['content']);
    }

    public function testThrowsWhenNeitherFileNorDescriptionAvailable(): void
    {
        $document = $this->document(DocumentFileType::Txt, '');

        $this->expectException(\RuntimeException::class);
        new DocumentProcessorService()->processDocument($document, null);
    }

    public function testExtractsPlainTextFile(): void
    {
        $path = $this->tempFile('.txt', 'Hello   world.  Second sentence.');
        $document = $this->document(DocumentFileType::Txt);

        $chunks = new DocumentProcessorService()->processDocument($document, $path);

        self::assertCount(1, $chunks);
        // cleanText() collapses runs of whitespace to a single space.
        self::assertSame('Hello world. Second sentence.', $chunks[0]['content']);
    }

    public function testExtractsHtmlFileStrippingTags(): void
    {
        $path = $this->tempFile('.html', '<html><body><p>Hello <b>world</b>.</p></body></html>');
        $document = $this->document(DocumentFileType::Html);

        $chunks = new DocumentProcessorService()->processDocument($document, $path);

        self::assertSame('Hello world.', $chunks[0]['content']);
    }

    public function testExtractsJsonFileAsPrettyPrintedText(): void
    {
        $path = $this->tempFile('.json', '{"a":1,"b":"text"}');
        $document = $this->document(DocumentFileType::Json);

        $chunks = new DocumentProcessorService()->processDocument($document, $path);

        // cleanText() strips JSON punctuation ({}":,) it doesn't recognize,
        // so only the alphanumeric content survives -- verifies extraction
        // ran (valid JSON decoded+re-encoded) without depending on exact
        // punctuation survival, which cleanText() isn't meant to preserve.
        self::assertStringContainsString('a', $chunks[0]['content']);
        self::assertStringContainsString('text', $chunks[0]['content']);
    }

    public function testThrowsOnInvalidJson(): void
    {
        $path = $this->tempFile('.json', '{not valid json');
        $document = $this->document(DocumentFileType::Json);

        $this->expectException(\RuntimeException::class);
        new DocumentProcessorService()->processDocument($document, $path);
    }

    public function testExtractsDocxDocumentXml(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'doc_processor_test_') . '.docx';
        $this->tempFiles[] = $path;

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString(
            'word/document.xml',
            '<w:document><w:body><w:p><w:r><w:t>Hello docx world.</w:t></w:r></w:p></w:body></w:document>',
        );
        $zip->close();

        $document = $this->document(DocumentFileType::Docx);
        $chunks = new DocumentProcessorService()->processDocument($document, $path);

        self::assertSame('Hello docx world.', $chunks[0]['content']);
    }

    public function testChunkMetadataReflectsDocument(): void
    {
        $path = $this->tempFile('.txt', 'Some content.');
        $document = $this->document(DocumentFileType::Txt);

        $chunks = new DocumentProcessorService()->processDocument($document, $path);

        self::assertSame('Test document', $chunks[0]['metadata']['document_title']);
        self::assertSame('txt', $chunks[0]['metadata']['document_type']);
        self::assertSame(0, $chunks[0]['start_position']);
    }

    public function testLongTextIsSplitIntoMultipleOverlappingChunks(): void
    {
        // Well past CHUNK_SIZE (1000 chars) so it must split; sentences give
        // cleanText()/createChunks() natural boundaries to break on.
        $text = str_repeat('This is a moderately long sentence for chunking purposes. ', 60);
        $path = $this->tempFile('.txt', $text);
        $document = $this->document(DocumentFileType::Txt);

        $chunks = new DocumentProcessorService()->processDocument($document, $path);

        self::assertGreaterThan(1, count($chunks));
        $counter = count($chunks);
        // Each chunk after the first starts before the previous one ends
        // (overlap), and positions are non-decreasing across the sequence.
        for ($i = 1; $i < $counter; ++$i) {
            self::assertLessThan($chunks[$i - 1]['end_position'], $chunks[$i]['start_position']);
            self::assertGreaterThan($chunks[$i - 1]['start_position'], $chunks[$i]['start_position']);
        }
    }
}
