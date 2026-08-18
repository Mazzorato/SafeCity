<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le prix horaire demandé pour les parkings gratuits et payants.
 */
final class Version20260801120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un tarif horaire précis au centime sur chaque parking.';
    }

    public function up(Schema $schema): void
    {
        // La colonne est temporairement nullable afin de préserver et compléter
        // toutes les lignes locales déjà présentes avant la contrainte finale.
        $this->addSql('ALTER TABLE parking ADD hourly_rate NUMERIC(6, 2) DEFAULT NULL');
        $this->addSql('UPDATE parking SET hourly_rate = CASE WHEN is_free THEN 0.00 ELSE 1.80 END');
        $this->addSql('ALTER TABLE parking ALTER hourly_rate SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parking DROP hourly_rate');
    }
}


