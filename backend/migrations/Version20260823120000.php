<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `audit_log` table backing App\EventListener\AuditLogListener -- append-only
 * history of create/update/delete actions on admin Sylius resources
 * (docs/BACKLOG.md "Journal d'audit des actions admin").
 */
final class Version20260823120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audit_log table for admin CRUD traceability';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(20) NOT NULL, resource_type VARCHAR(60) NOT NULL, resource_id VARCHAR(40) DEFAULT NULL, resource_label VARCHAR(255) DEFAULT NULL, actor_email VARCHAR(180) DEFAULT NULL, occurred_at DATETIME NOT NULL, INDEX audit_log_resource_idx (resource_type, resource_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
    }
}
