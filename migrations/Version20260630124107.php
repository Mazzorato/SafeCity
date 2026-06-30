<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630124107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE "user" ADD is_verified BOOLEAN NOT NULL');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)');
}

    public function down(Schema $schema): void
{
    $this->addSql('DROP INDEX UNIQ_IDENTIFIER_EMAIL');
    $this->addSql('ALTER TABLE "user" DROP is_verified');
}
}
