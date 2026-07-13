<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260713121012 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE news ALTER is_featured DROP DEFAULT');
        $this->addSql('ALTER TABLE report ADD city_id INT NOT NULL');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F77848BAC62AF FOREIGN KEY (city_id) REFERENCES city (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_C42F77848BAC62AF ON report (city_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE news ALTER is_featured SET DEFAULT false');
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F77848BAC62AF');
        $this->addSql('DROP INDEX IDX_C42F77848BAC62AF');
        $this->addSql('ALTER TABLE report DROP city_id');
    }
}
