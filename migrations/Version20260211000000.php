<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create password_reset_tokens table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE password_reset_tokens (
            id INT AUTO_INCREMENT NOT NULL, 
            user_id INT NOT NULL, 
            token VARCHAR(64) NOT NULL, 
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            expires_at DATETIME NOT NULL, 
            channel VARCHAR(20) NOT NULL, 
            used TINYINT(1) NOT NULL DEFAULT 0, 
            UNIQUE INDEX UNIQ_PASSWORD_RESET_TOKEN (token), 
            INDEX IDX_PASSWORD_RESET_USER (user_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('ALTER TABLE password_reset_tokens 
            ADD CONSTRAINT FK_PASSWORD_RESET_USER 
            FOREIGN KEY (user_id) 
            REFERENCES user (id) 
            ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY FK_PASSWORD_RESET_USER');
        $this->addSql('DROP TABLE password_reset_tokens');
    }
}
