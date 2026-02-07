<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207142240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(50) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id INT DEFAULT NULL, details LONGTEXT DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_F6E1C0F5A76ED395 (user_id), INDEX idx_audit_created_at (created_at), INDEX idx_audit_entity_type (entity_type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE coach_application (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, certifications LONGTEXT NOT NULL, experience LONGTEXT NOT NULL, submitted_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, review_comment LONGTEXT DEFAULT NULL, documents VARCHAR(255) DEFAULT NULL, cv_file VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_80818310A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE matches (id INT AUTO_INCREMENT NOT NULL, equipe1 VARCHAR(255) NOT NULL, equipe2 VARCHAR(255) NOT NULL, score INT NOT NULL, date_match DATE NOT NULL, prix VARCHAR(255) NOT NULL, tournoi_id INT NOT NULL, INDEX IDX_62615BAF607770A (tournoi_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, message LONGTEXT NOT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, link VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planning (IDplanning INT AUTO_INCREMENT NOT NULL, image VARCHAR(255) DEFAULT NULL, date DATE NOT NULL, time TIME NOT NULL, localisation VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, need_partner TINYINT NOT NULL, level VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY (IDplanning)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, max_members INT NOT NULL, is_active TINYINT NOT NULL, owner_id INT NOT NULL, INDEX IDX_C4E0A61F7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team_membership (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, joined_at DATETIME NOT NULL, invited_at DATETIME DEFAULT NULL, responded_at DATETIME DEFAULT NULL, user_id INT NOT NULL, team_id INT NOT NULL, INDEX IDX_B826A040A76ED395 (user_id), INDEX IDX_B826A040296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tournoi (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, description LONGTEXT DEFAULT NULL, image VARCHAR(255) NOT NULL, type_tournoi VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE training_session (idtraining INT AUTO_INCREMENT NOT NULL, status VARCHAR(50) NOT NULL, joined_at DATETIME NOT NULL, ID_planning INT NOT NULL, IDcurrent_user INT NOT NULL, INDEX IDX_D7A45DA4D83DF64 (ID_planning), INDEX IDX_D7A45DA7A95F30B (IDcurrent_user), PRIMARY KEY (idtraining)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(50) NOT NULL, roles_json LONGTEXT NOT NULL, password VARCHAR(255) NOT NULL, account_status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, last_login DATETIME NOT NULL, full_name VARCHAR(100) DEFAULT NULL, bio VARCHAR(255) DEFAULT NULL, profile_picture VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE coach_application ADD CONSTRAINT FK_80818310A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BAF607770A FOREIGN KEY (tournoi_id) REFERENCES tournoi (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE team_membership ADD CONSTRAINT FK_B826A040A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE team_membership ADD CONSTRAINT FK_B826A040296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE training_session ADD CONSTRAINT FK_D7A45DA4D83DF64 FOREIGN KEY (ID_planning) REFERENCES planning (IDplanning)');
        $this->addSql('ALTER TABLE training_session ADD CONSTRAINT FK_D7A45DA7A95F30B FOREIGN KEY (IDcurrent_user) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_log DROP FOREIGN KEY FK_F6E1C0F5A76ED395');
        $this->addSql('ALTER TABLE coach_application DROP FOREIGN KEY FK_80818310A76ED395');
        $this->addSql('ALTER TABLE matches DROP FOREIGN KEY FK_62615BAF607770A');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F7E3C61F9');
        $this->addSql('ALTER TABLE team_membership DROP FOREIGN KEY FK_B826A040A76ED395');
        $this->addSql('ALTER TABLE team_membership DROP FOREIGN KEY FK_B826A040296CD8AE');
        $this->addSql('ALTER TABLE training_session DROP FOREIGN KEY FK_D7A45DA4D83DF64');
        $this->addSql('ALTER TABLE training_session DROP FOREIGN KEY FK_D7A45DA7A95F30B');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE coach_application');
        $this->addSql('DROP TABLE matches');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE planning');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE team_membership');
        $this->addSql('DROP TABLE tournoi');
        $this->addSql('DROP TABLE training_session');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
