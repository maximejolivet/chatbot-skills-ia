<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * FULLTEXT index backing the lexical half of hybrid search (BM25-style
 * relevance via MariaDB's native MATCH()...AGAINST(), InnoDB) --
 * App\VectorConnector\VectorSearchService now fuses this with Qdrant's
 * vector search (Reciprocal Rank Fusion) instead of ranking on vector
 * similarity alone.
 */
final class Version20260822220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add FULLTEXT index on document_chunk.content for hybrid (BM25 + vector) search';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_chunk ADD FULLTEXT INDEX document_chunk_content_fulltext (content)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_chunk DROP INDEX document_chunk_content_fulltext');
    }
}
