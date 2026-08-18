<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne les index et les commentaires techniques de modération et de routage avec le mapping Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_B3D05FEAE1CFE6F5 RENAME TO IDX_9B9E4516E1CFE6F5');
        $this->addSql('ALTER INDEX IDX_B3D05FEAF675F31B RENAME TO IDX_9B9E4516F675F31B');
        $this->addSql('ALTER INDEX IDX_B3D05FEAD0AFA354 RENAME TO IDX_9B9E4516D0AFA354');
        $this->addSql("COMMENT ON COLUMN moderation_case.reported_at IS ''");
        $this->addSql("COMMENT ON COLUMN moderation_case.moderated_at IS ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_9B9E4516E1CFE6F5 RENAME TO IDX_B3D05FEAE1CFE6F5');
        $this->addSql('ALTER INDEX IDX_9B9E4516F675F31B RENAME TO IDX_B3D05FEAF675F31B');
        $this->addSql('ALTER INDEX IDX_9B9E4516D0AFA354 RENAME TO IDX_B3D05FEAD0AFA354');
        $this->addSql("COMMENT ON COLUMN moderation_case.reported_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN moderation_case.moderated_at IS '(DC2Type:datetime_immutable)'");
    }
}
