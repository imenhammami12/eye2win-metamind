<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208202244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE matches DROP FOREIGN KEY `FK_62615BAF607770A`');
        $this->addSql('DROP TABLE matches');
        $this->addSql('DROP TABLE tournoi');
        $this->addSql('ALTER TABLE team_membership DROP responded_at, CHANGE role role VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE joined_at joined_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD totp_secret VARCHAR(255) DEFAULT NULL, ADD is_totp_enabled TINYINT DEFAULT 0 NOT NULL, ADD backup_codes_json LONGTEXT DEFAULT NULL, ADD totp_enabled_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE matches (id INT AUTO_INCREMENT NOT NULL, equipe1 VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, equipe2 VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, score INT NOT NULL, date_match DATE NOT NULL, prix VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, tournoi_id INT NOT NULL, INDEX IDX_62615BAF607770A (tournoi_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE tournoi (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_debut DATE NOT NULL, date_fin DATE NOT NULL, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, image VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, type_tournoi VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT `FK_62615BAF607770A` FOREIGN KEY (tournoi_id) REFERENCES tournoi (id)');
        $this->addSql('ALTER TABLE team_membership ADD responded_at DATETIME DEFAULT NULL, CHANGE role role VARCHAR(20) NOT NULL, CHANGE status status VARCHAR(20) NOT NULL, CHANGE joined_at joined_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `user` DROP totp_secret, DROP is_totp_enabled, DROP backup_codes_json, DROP totp_enabled_at');
    }
}
