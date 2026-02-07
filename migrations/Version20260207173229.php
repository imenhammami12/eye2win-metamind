<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207173229 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE player_stat (id INT AUTO_INCREMENT NOT NULL, score INT NOT NULL, accuracy DOUBLE PRECISION NOT NULL, actions_count INT NOT NULL, videomatch_id INT NOT NULL, INDEX IDX_82A2AF12BB1BA406 (videomatch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE player_stat ADD CONSTRAINT FK_82A2AF12BB1BA406 FOREIGN KEY (videomatch_id) REFERENCES video (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE player_stat DROP FOREIGN KEY FK_82A2AF12BB1BA406');
        $this->addSql('DROP TABLE player_stat');
    }
}
