<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260710112019 extends AbstractMigration
{
public function getDescription(): string
    {
return '';
    }
public function up(Schema $schema): void
    {
// this up() migration is auto-generated, please modify it to your needs
$this->addSql('ALTER TABLE city ADD latitude NUMERIC(10, 7) DEFAULT NULL');
$this->addSql('ALTER TABLE city ADD longitude NUMERIC(10, 7) DEFAULT NULL');
$this->addSql('ALTER TABLE news ADD is_featured BOOLEAN NOT NULL DEFAULT false');
$this->addSql('ALTER TABLE news ALTER category TYPE VARCHAR(255)');
    }
public function down(Schema $schema): void
    {
// this down() migration is auto-generated, please modify it to your needs
$this->addSql('ALTER TABLE city DROP latitude');
$this->addSql('ALTER TABLE city DROP longitude');
$this->addSql('ALTER TABLE news DROP is_featured');
$this->addSql('ALTER TABLE news ALTER category TYPE VARCHAR(50)');
    }
}