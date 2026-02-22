<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create dynamic guides domain: game, agent, guide_video';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(50) NOT NULL, icon VARCHAR(255) DEFAULT NULL, color VARCHAR(7) NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_232B318CCFCE0E14 (name), UNIQUE INDEX UNIQ_232B318C989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE agent (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(50) NOT NULL, image VARCHAR(255) DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_268B9C9BE48FD905 (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE agent ADD CONSTRAINT FK_268B9C9BE48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE guide_video (id INT AUTO_INCREMENT NOT NULL, uploaded_by_id INT NOT NULL, game_id INT NOT NULL, agent_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, video_url VARCHAR(255) NOT NULL, thumbnail VARCHAR(255) DEFAULT NULL, map VARCHAR(50) NOT NULL, likes INT NOT NULL, views INT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, approved_at DATETIME DEFAULT NULL, INDEX IDX_22DF0EA5AA2082C9 (uploaded_by_id), INDEX IDX_22DF0EA5E48FD905 (game_id), INDEX IDX_22DF0EA534155D8 (agent_id), INDEX IDX_22DF0EA56BF700BD (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE guide_video ADD CONSTRAINT FK_22DF0EA5AA2082C9 FOREIGN KEY (uploaded_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE guide_video ADD CONSTRAINT FK_22DF0EA5E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guide_video ADD CONSTRAINT FK_22DF0EA534155D8 FOREIGN KEY (agent_id) REFERENCES agent (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guide_video DROP FOREIGN KEY FK_22DF0EA5AA2082C9');
        $this->addSql('ALTER TABLE guide_video DROP FOREIGN KEY FK_22DF0EA5E48FD905');
        $this->addSql('ALTER TABLE guide_video DROP FOREIGN KEY FK_22DF0EA534155D8');
        $this->addSql('ALTER TABLE agent DROP FOREIGN KEY FK_268B9C9BE48FD905');
        $this->addSql('DROP TABLE guide_video');
        $this->addSql('DROP TABLE agent');
        $this->addSql('DROP TABLE game');
    }
}
