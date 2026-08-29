<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class VersionBase extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Baseline schema';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ai_agent (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(200) NOT NULL, description LONGTEXT NOT NULL, system_prompt LONGTEXT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_7A3CA7D85E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE ai_agent_workflow (ai_agent_id INT NOT NULL, workflow_id INT NOT NULL, INDEX IDX_812831432BF2F76B (ai_agent_id), INDEX IDX_812831432C7C2CBA (workflow_id), PRIMARY KEY (ai_agent_id, workflow_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE ai_provider_config (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(200) NOT NULL, `usage` VARCHAR(20) NOT NULL, provider VARCHAR(20) NOT NULL, api_endpoint VARCHAR(255) DEFAULT NULL, api_key LONGTEXT DEFAULT NULL, model VARCHAR(200) DEFAULT NULL, base_url VARCHAR(255) DEFAULT NULL, is_active TINYINT NOT NULL, is_default TINYINT NOT NULL, last_test_status VARCHAR(10) NOT NULL, last_tested_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_6F0A027A5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE audit_log (id INT AUTO_INCREMENT NOT NULL, occurred_at DATETIME NOT NULL, action VARCHAR(20) NOT NULL, resource_type VARCHAR(60) NOT NULL, resource_id VARCHAR(40) DEFAULT NULL, resource_label VARCHAR(255) DEFAULT NULL, actor_email VARCHAR(180) DEFAULT NULL, INDEX audit_log_resource_idx (resource_type, resource_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE collection (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(200) NOT NULL, description LONGTEXT NOT NULL, is_common TINYINT NOT NULL, created_at DATETIME NOT NULL, agent_id INT DEFAULT NULL, vector_index_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_FC4D65325E237E06 (name), UNIQUE INDEX UNIQ_FC4D65323414710B (agent_id), INDEX IDX_FC4D65321214F412 (vector_index_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE conversation (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_active TINYINT NOT NULL, visitor_first_name VARCHAR(100) DEFAULT NULL, visitor_last_name VARCHAR(100) DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_8A8E26E9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT NOT NULL, file_path VARCHAR(255) DEFAULT NULL, file_type VARCHAR(10) NOT NULL, uploaded_at DATETIME NOT NULL, file_size INT NOT NULL, status VARCHAR(20) NOT NULL, processing_error LONGTEXT NOT NULL, metadata JSON NOT NULL, category_id INT DEFAULT NULL, collection_id INT DEFAULT NULL, INDEX IDX_D8698A7612469DE2 (category_id), INDEX IDX_D8698A76514956FD (collection_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE document_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_898DE8985E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE document_chunk (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, chunk_index INT NOT NULL, start_position INT NOT NULL, end_position INT NOT NULL, vector_id VARCHAR(64) DEFAULT NULL, metadata JSON NOT NULL, created_at DATETIME NOT NULL, document_id INT NOT NULL, INDEX IDX_FCA7075CC33F7837 (document_id), UNIQUE INDEX document_chunk_index_unique (document_id, chunk_index), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE faq (id INT AUTO_INCREMENT NOT NULL, question VARCHAR(500) NOT NULL, answer LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_active TINYINT NOT NULL, priority INT NOT NULL, is_highlighted TINYINT NOT NULL, tags JSON NOT NULL, category_id INT DEFAULT NULL, INDEX IDX_E8FF75CC12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(10) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, metadata JSON NOT NULL, feedback VARCHAR(10) DEFAULT NULL, conversation_id INT NOT NULL, INDEX IDX_B6BD307F9AC0396 (conversation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE search_query (id INT AUTO_INCREMENT NOT NULL, query VARCHAR(500) NOT NULL, results_count INT NOT NULL, execution_time DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, metadata JSON NOT NULL, vector_index_id INT NOT NULL, INDEX IDX_108876021214F412 (vector_index_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE vector_index (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT NOT NULL, collection_id VARCHAR(100) NOT NULL, dimension INT NOT NULL, created_at DATETIME NOT NULL, is_active TINYINT NOT NULL, metadata JSON NOT NULL, UNIQUE INDEX UNIQ_CF0BAC805E237E06 (name), UNIQUE INDEX UNIQ_CF0BAC80514956FD (collection_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE workflow (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(200) NOT NULL, description LONGTEXT NOT NULL, trigger_type VARCHAR(20) NOT NULL, trigger_config JSON NOT NULL, parameters_schema JSON NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_active TINYINT NOT NULL, UNIQUE INDEX UNIQ_65C598165E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE workflow_execution (id INT AUTO_INCREMENT NOT NULL, input_data JSON NOT NULL, output_data JSON NOT NULL, status VARCHAR(20) NOT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, error_message LONGTEXT NOT NULL, execution_log JSON NOT NULL, created_at DATETIME NOT NULL, workflow_id INT NOT NULL, conversation_id INT DEFAULT NULL, triggered_by_id INT DEFAULT NULL, INDEX IDX_FF094DBF2C7C2CBA (workflow_id), INDEX IDX_FF094DBF9AC0396 (conversation_id), INDEX IDX_FF094DBF63C5923F (triggered_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE workflow_step (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(200) NOT NULL, step_type VARCHAR(20) NOT NULL, `order` INT NOT NULL, configuration JSON NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, workflow_id INT NOT NULL, INDEX IDX_626EE072C7C2CBA (workflow_id), UNIQUE INDEX workflow_step_order_unique (workflow_id, `order`), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE ai_agent_workflow ADD CONSTRAINT FK_812831432BF2F76B FOREIGN KEY (ai_agent_id) REFERENCES ai_agent (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ai_agent_workflow ADD CONSTRAINT FK_812831432C7C2CBA FOREIGN KEY (workflow_id) REFERENCES workflow (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE collection ADD CONSTRAINT FK_FC4D65323414710B FOREIGN KEY (agent_id) REFERENCES ai_agent (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE collection ADD CONSTRAINT FK_FC4D65321214F412 FOREIGN KEY (vector_index_id) REFERENCES vector_index (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7612469DE2 FOREIGN KEY (category_id) REFERENCES document_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76514956FD FOREIGN KEY (collection_id) REFERENCES collection (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document_chunk ADD CONSTRAINT FK_FCA7075CC33F7837 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE faq ADD CONSTRAINT FK_E8FF75CC12469DE2 FOREIGN KEY (category_id) REFERENCES document_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE search_query ADD CONSTRAINT FK_108876021214F412 FOREIGN KEY (vector_index_id) REFERENCES vector_index (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workflow_execution ADD CONSTRAINT FK_FF094DBF2C7C2CBA FOREIGN KEY (workflow_id) REFERENCES workflow (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workflow_execution ADD CONSTRAINT FK_FF094DBF9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE workflow_execution ADD CONSTRAINT FK_FF094DBF63C5923F FOREIGN KEY (triggered_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE workflow_step ADD CONSTRAINT FK_626EE072C7C2CBA FOREIGN KEY (workflow_id) REFERENCES workflow (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_chunk ADD FULLTEXT INDEX document_chunk_content_fulltext (content)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ai_agent_workflow DROP FOREIGN KEY FK_812831432BF2F76B');
        $this->addSql('ALTER TABLE ai_agent_workflow DROP FOREIGN KEY FK_812831432C7C2CBA');
        $this->addSql('ALTER TABLE collection DROP FOREIGN KEY FK_FC4D65323414710B');
        $this->addSql('ALTER TABLE collection DROP FOREIGN KEY FK_FC4D65321214F412');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9A76ED395');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7612469DE2');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76514956FD');
        $this->addSql('ALTER TABLE document_chunk DROP FOREIGN KEY FK_FCA7075CC33F7837');
        $this->addSql('ALTER TABLE faq DROP FOREIGN KEY FK_E8FF75CC12469DE2');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE search_query DROP FOREIGN KEY FK_108876021214F412');
        $this->addSql('ALTER TABLE workflow_execution DROP FOREIGN KEY FK_FF094DBF2C7C2CBA');
        $this->addSql('ALTER TABLE workflow_execution DROP FOREIGN KEY FK_FF094DBF9AC0396');
        $this->addSql('ALTER TABLE workflow_execution DROP FOREIGN KEY FK_FF094DBF63C5923F');
        $this->addSql('ALTER TABLE workflow_step DROP FOREIGN KEY FK_626EE072C7C2CBA');
        $this->addSql('ALTER TABLE document_chunk DROP INDEX document_chunk_content_fulltext');
        $this->addSql('DROP TABLE ai_agent');
        $this->addSql('DROP TABLE ai_agent_workflow');
        $this->addSql('DROP TABLE ai_provider_config');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE collection');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE document_category');
        $this->addSql('DROP TABLE document_chunk');
        $this->addSql('DROP TABLE faq');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE search_query');
        $this->addSql('DROP TABLE vector_index');
        $this->addSql('DROP TABLE workflow');
        $this->addSql('DROP TABLE workflow_execution');
        $this->addSql('DROP TABLE workflow_step');
    }
}
