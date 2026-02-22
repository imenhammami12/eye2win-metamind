<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222010100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ValorantTracker module: matchs, equipes, joueurs et statistiques';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE valorant_match (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, tracker_match_id VARCHAR(120) NOT NULL, map_name VARCHAR(120) DEFAULT NULL, mode VARCHAR(120) DEFAULT NULL, played_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', duration_seconds INT DEFAULT NULL, score_team_a INT DEFAULT NULL, score_team_b INT DEFAULT NULL, status VARCHAR(20) NOT NULL, raw_data JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', archived_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_E311F4887E3C61F9 (owner_id), UNIQUE INDEX uniq_valorant_match_owner (owner_id, tracker_match_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('CREATE TABLE valorant_equipe (id INT AUTO_INCREMENT NOT NULL, match_id INT NOT NULL, name VARCHAR(120) NOT NULL, side VARCHAR(30) DEFAULT NULL, score INT DEFAULT NULL, INDEX IDX_6D4F5FF67E5A11A4 (match_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE valorant_joueur (id INT AUTO_INCREMENT NOT NULL, match_id INT NOT NULL, equipe_id INT DEFAULT NULL, tracker_player_id VARCHAR(120) DEFAULT NULL, riot_name VARCHAR(120) NOT NULL, riot_tag VARCHAR(20) DEFAULT NULL, agent VARCHAR(50) DEFAULT NULL, INDEX IDX_5CFB13F57E5A11A4 (match_id), INDEX IDX_5CFB13F5D86B4A29 (equipe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE valorant_statistique (id INT AUTO_INCREMENT NOT NULL, joueur_id INT NOT NULL, kills INT NOT NULL, deaths INT NOT NULL, assists INT NOT NULL, headshots INT DEFAULT NULL, damage INT DEFAULT NULL, weapons JSON DEFAULT NULL, timings JSON DEFAULT NULL, extra JSON DEFAULT NULL, UNIQUE INDEX UNIQ_3B95E53F6DFA9E7B (joueur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE valorant_match ADD CONSTRAINT FK_E311F4887E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE valorant_equipe ADD CONSTRAINT FK_6D4F5FF67E5A11A4 FOREIGN KEY (match_id) REFERENCES valorant_match (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE valorant_joueur ADD CONSTRAINT FK_5CFB13F57E5A11A4 FOREIGN KEY (match_id) REFERENCES valorant_match (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE valorant_joueur ADD CONSTRAINT FK_5CFB13F5D86B4A29 FOREIGN KEY (equipe_id) REFERENCES valorant_equipe (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE valorant_statistique ADD CONSTRAINT FK_3B95E53F6DFA9E7B FOREIGN KEY (joueur_id) REFERENCES valorant_joueur (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE valorant_statistique DROP FOREIGN KEY FK_3B95E53F6DFA9E7B');
        $this->addSql('ALTER TABLE valorant_joueur DROP FOREIGN KEY FK_5CFB13F57E5A11A4');
        $this->addSql('ALTER TABLE valorant_joueur DROP FOREIGN KEY FK_5CFB13F5D86B4A29');
        $this->addSql('ALTER TABLE valorant_equipe DROP FOREIGN KEY FK_6D4F5FF67E5A11A4');
        $this->addSql('ALTER TABLE valorant_match DROP FOREIGN KEY FK_E311F4887E3C61F9');

        $this->addSql('DROP TABLE valorant_statistique');
        $this->addSql('DROP TABLE valorant_joueur');
        $this->addSql('DROP TABLE valorant_equipe');
        $this->addSql('DROP TABLE valorant_match');
    }
}
