<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220225322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
$this->addSql('CREATE TABLE live_stream (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, coin_price INT NOT NULL, status VARCHAR(50) NOT NULL, stream_key VARCHAR(191) NOT NULL, created_at DATETIME NOT NULL, started_at DATETIME DEFAULT NULL, ended_at DATETIME DEFAULT NULL, coach_id INT NOT NULL, UNIQUE INDEX UNIQ_93BF08C820F533D7 (stream_key(191)), INDEX IDX_93BF08C83C105691 (coach_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE live_stream ADD CONSTRAINT FK_93BF08C83C105691 FOREIGN KEY (coach_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE coin_purchase ADD CONSTRAINT FK_ABE21FE0A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE live_access ADD CONSTRAINT FK_653F9D80A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE live_access ADD CONSTRAINT FK_653F9D806AFA264E FOREIGN KEY (live_stream_id) REFERENCES live_stream (id)');
        $this->addSql('ALTER TABLE user ADD coin_balance INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE live_stream DROP FOREIGN KEY FK_93BF08C83C105691');
        $this->addSql('DROP TABLE live_stream');
        $this->addSql('ALTER TABLE coin_purchase DROP FOREIGN KEY FK_ABE21FE0A76ED395');
        $this->addSql('ALTER TABLE live_access DROP FOREIGN KEY FK_653F9D80A76ED395');
        $this->addSql('ALTER TABLE live_access DROP FOREIGN KEY FK_653F9D806AFA264E');
        $this->addSql('ALTER TABLE `user` DROP coin_balance');
    }
}
