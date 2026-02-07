<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207201738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE planning (IDplanning INT AUTO_INCREMENT NOT NULL, image VARCHAR(255) DEFAULT NULL, date DATE NOT NULL, time TIME NOT NULL, localisation VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, need_partner TINYINT NOT NULL, level VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY (IDplanning)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE training_session (idtraining INT AUTO_INCREMENT NOT NULL, status VARCHAR(50) NOT NULL, joined_at DATETIME NOT NULL, ID_planning INT NOT NULL, IDcurrent_user INT NOT NULL, INDEX IDX_D7A45DA4D83DF64 (ID_planning), INDEX IDX_D7A45DA7A95F30B (IDcurrent_user), PRIMARY KEY (idtraining)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE training_session ADD CONSTRAINT FK_D7A45DA4D83DF64 FOREIGN KEY (ID_planning) REFERENCES planning (IDplanning)');
        $this->addSql('ALTER TABLE training_session ADD CONSTRAINT FK_D7A45DA7A95F30B FOREIGN KEY (IDcurrent_user) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE training_session DROP FOREIGN KEY FK_D7A45DA4D83DF64');
        $this->addSql('ALTER TABLE training_session DROP FOREIGN KEY FK_D7A45DA7A95F30B');
        $this->addSql('DROP TABLE planning');
        $this->addSql('DROP TABLE training_session');
    }
}
