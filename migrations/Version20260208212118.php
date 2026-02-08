<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208212118 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE training_session DROP FOREIGN KEY `FK_D7A45DA4D83DF64`');
        $this->addSql('ALTER TABLE training_session DROP FOREIGN KEY `FK_D7A45DA7A95F30B`');
        $this->addSql('DROP TABLE planning');
        $this->addSql('DROP TABLE training_session');
        $this->addSql('ALTER TABLE video ADD visibility VARCHAR(10) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE planning (IDplanning INT AUTO_INCREMENT NOT NULL, image VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date DATE NOT NULL, time TIME NOT NULL, localisation VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, need_partner TINYINT NOT NULL, level VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, type VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY (IDplanning)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE training_session (idtraining INT AUTO_INCREMENT NOT NULL, status VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, joined_at DATETIME NOT NULL, ID_planning INT NOT NULL, IDcurrent_user INT NOT NULL, INDEX IDX_D7A45DA4D83DF64 (ID_planning), INDEX IDX_D7A45DA7A95F30B (IDcurrent_user), PRIMARY KEY (idtraining)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE training_session ADD CONSTRAINT `FK_D7A45DA4D83DF64` FOREIGN KEY (ID_planning) REFERENCES planning (IDplanning)');
        $this->addSql('ALTER TABLE training_session ADD CONSTRAINT `FK_D7A45DA7A95F30B` FOREIGN KEY (IDcurrent_user) REFERENCES user (id)');
        $this->addSql('ALTER TABLE video DROP visibility');
    }
}
