<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211123845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE complaint (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(200) NOT NULL, description LONGTEXT NOT NULL, category VARCHAR(50) NOT NULL, status VARCHAR(20) NOT NULL, priority VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, resolved_at DATETIME DEFAULT NULL, admin_response LONGTEXT DEFAULT NULL, resolution_notes LONGTEXT DEFAULT NULL, attachment_path VARCHAR(255) DEFAULT NULL, submitted_by_id INT NOT NULL, assigned_to_id INT DEFAULT NULL, INDEX IDX_5F2732B579F7D87D (submitted_by_id), INDEX IDX_5F2732B5F4BD7827 (assigned_to_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE complaint ADD CONSTRAINT FK_5F2732B579F7D87D FOREIGN KEY (submitted_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE complaint ADD CONSTRAINT FK_5F2732B5F4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE complaint DROP FOREIGN KEY FK_5F2732B579F7D87D');
        $this->addSql('ALTER TABLE complaint DROP FOREIGN KEY FK_5F2732B5F4BD7827');
        $this->addSql('DROP TABLE complaint');
    }
}
