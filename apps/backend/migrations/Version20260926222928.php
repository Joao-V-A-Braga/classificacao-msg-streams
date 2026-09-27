<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Monitors: the user creates one pipeline per live/post and messages belong to it.
 */
final class Version20260926222928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Monitors (one pipeline per live/post, with its own tags); messages now belong to a monitor.';
    }

    public function up(Schema $schema): void
    {
        // Messages from the single-list MVP have no monitor to belong to; this is test data only.
        $this->addSql('DELETE FROM messages');
        $this->addSql('CREATE TABLE monitors (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, name VARCHAR(120) NOT NULL, source_type VARCHAR(32) NOT NULL, url VARCHAR(2048) NOT NULL, status VARCHAR(16) NOT NULL, tags JSON NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, runner_state VARCHAR(16) DEFAULT NULL, runner_detail VARCHAR(500) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_monitors_user_id ON monitors (user_id)');
        $this->addSql('CREATE INDEX idx_monitors_status ON monitors (status)');
        $this->addSql('ALTER TABLE messages ADD monitor_id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE messages ADD external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE messages DROP source');
        $this->addSql('ALTER TABLE messages DROP source_url');
        $this->addSql('ALTER TABLE messages ALTER label TYPE VARCHAR(40)');
        $this->addSql('CREATE INDEX idx_messages_monitor_received ON messages (monitor_id, received_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_messages_monitor_external ON messages (monitor_id, external_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM messages');
        $this->addSql('DROP TABLE monitors');
        $this->addSql('DROP INDEX idx_messages_monitor_received');
        $this->addSql('DROP INDEX uniq_messages_monitor_external');
        $this->addSql('ALTER TABLE messages ADD source VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE messages ADD source_url VARCHAR(2048) NOT NULL');
        $this->addSql('ALTER TABLE messages DROP monitor_id');
        $this->addSql('ALTER TABLE messages DROP external_id');
        $this->addSql('ALTER TABLE messages ALTER label TYPE VARCHAR(32)');
    }
}
