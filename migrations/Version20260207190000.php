<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260207190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add game type, public id, and duration to video';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video ADD game_type VARCHAR(100) DEFAULT NULL, ADD public_id VARCHAR(255) DEFAULT NULL, ADD duration DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video DROP game_type, DROP public_id, DROP duration');
    }
}
