<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `is_highlighted` column on `faq` -- lets an admin visually set a FAQ apart
 * in the public collection/widget independently of `priority` (which only
 * controls sort order), see App\Entity\Faq.
 */
final class Version20260825060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_highlighted column to faq for admin-controlled highlighting';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE faq ADD is_highlighted TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE faq DROP is_highlighted');
    }
}
