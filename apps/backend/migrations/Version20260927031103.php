<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Monitors can turn off the translation to English before Laya.
 */
final class Version20260927031103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Monitor setting: translate messages to English before Laya (on by default).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE monitors ADD translate_for_laya BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE monitors DROP translate_for_laya');
    }
}
