<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `priority` column on `faq` -- lets an admin choose the display order of
 * FAQ entries (public API collection + /admin/faqs grid), see
 * App\Entity\Faq and App\Doctrine\FaqActiveCollectionExtension.
 */
final class Version20260824180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add priority column to faq for admin-controlled display order';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE faq ADD priority INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE faq DROP priority');
    }
}
