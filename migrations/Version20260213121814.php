<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213121814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add face recognition fields to user table';
    }

    public function up(Schema $schema): void
    {
        // Ajouter les colonnes pour la reconnaissance faciale
        $this->addSql('ALTER TABLE `user` ADD face_descriptor LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD face_image VARCHAR(255) DEFAULT NULL');
        
        // Si vous avez d'autres modifications, corrigez la syntaxe RENAME INDEX
        // Remplacez: RENAME INDEX uniq_password_reset_token TO UNIQ_3967A2165F37A13B
        // Par:
        // $this->addSql('ALTER TABLE password_reset_token DROP INDEX uniq_password_reset_token');
        // $this->addSql('ALTER TABLE password_reset_token ADD UNIQUE INDEX UNIQ_3967A2165F37A13B (token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP face_descriptor');
        $this->addSql('ALTER TABLE `user` DROP face_image');
    }
}