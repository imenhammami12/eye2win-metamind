<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260201222654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE video ADD uploaded_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE video ADD CONSTRAINT FK_7CC7DA2CA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_7CC7DA2CA2B28FE8 ON video (uploaded_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE video DROP FOREIGN KEY FK_7CC7DA2CA2B28FE8');
        $this->addSql('DROP INDEX IDX_7CC7DA2CA2B28FE8 ON video');
        $this->addSql('ALTER TABLE video DROP uploaded_by_id');
    }
}
