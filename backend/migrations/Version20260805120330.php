<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260805120330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE search_query DROP CONSTRAINT fk_108876021214f412');
        $this->addSql('ALTER TABLE search_query ADD CONSTRAINT FK_108876021214F412 FOREIGN KEY (vector_index_id) REFERENCES vector_index (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE search_query DROP CONSTRAINT FK_108876021214F412');
        $this->addSql('ALTER TABLE search_query ADD CONSTRAINT fk_108876021214f412 FOREIGN KEY (vector_index_id) REFERENCES vector_index (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
