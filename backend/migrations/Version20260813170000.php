<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Re-adds message.feedback (thumbs up/down), dropped from the squashed
 * baseline (Version20260813160000) when the feature was briefly removed,
 * then restored on top instead of editing that already-deployed migration.
 */
final class Version20260813170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message.feedback (thumbs up/down)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD feedback VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP feedback');
    }
}
