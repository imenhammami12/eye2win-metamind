<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208213530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE matches (id INT AUTO_INCREMENT NOT NULL, equipe1 VARCHAR(255) NOT NULL, equipe2 VARCHAR(255) NOT NULL, score INT NOT NULL, date_match DATE NOT NULL, prix VARCHAR(255) NOT NULL, tournoi_id INT NOT NULL, INDEX IDX_62615BAF607770A (tournoi_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tournoi (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, description LONGTEXT DEFAULT NULL, image VARCHAR(255) NOT NULL, type_tournoi VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BAF607770A FOREIGN KEY (tournoi_id) REFERENCES tournoi (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE matches DROP FOREIGN KEY FK_62615BAF607770A');
        $this->addSql('DROP TABLE matches');
        $this->addSql('DROP TABLE tournoi');
    }
}
