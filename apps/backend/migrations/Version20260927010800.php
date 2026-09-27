<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Monitors can import the comments an Instagram post already had.
 */
final class Version20260927010800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Monitor setting: import the existing comments of an Instagram post on start.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE monitors ADD include_existing_comments BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE monitors DROP include_existing_comments');
    }
}
