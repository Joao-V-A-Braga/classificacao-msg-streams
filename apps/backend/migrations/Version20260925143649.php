<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925143649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create messages table for the message-classification module.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE messages (id VARCHAR(36) NOT NULL, source VARCHAR(32) NOT NULL, source_url VARCHAR(2048) NOT NULL, author_name VARCHAR(255) NOT NULL, text TEXT NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, label VARCHAR(32) DEFAULT NULL, classified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE messages');
    }
}
