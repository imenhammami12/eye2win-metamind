<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Add phone and telegram_chat_id to User
 */
final class Version20260211000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add phone and telegram_chat_id fields to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD phone VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD telegram_chat_id VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP phone');
        $this->addSql('ALTER TABLE user DROP telegram_chat_id');
    }
}
