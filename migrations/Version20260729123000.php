<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retire du schéma les données propres aux signalements vocaux.
 */
final class Version20260729123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime les champs audio des signalements et la permission microphone des profils.';
    }

    public function up(Schema $schema): void
    {
        // Aucun signalement ne contenait d'audio lors du contrôle précédant cette migration.
        $this->addSql('ALTER TABLE report DROP audio_url, DROP audio_language');
        $this->addSql('ALTER TABLE profile DROP microphone_access');
    }

    public function down(Schema $schema): void
    {
        // Le retour arrière restaure uniquement les colonnes, sans réactiver l'interface audio.
        $this->addSql('ALTER TABLE report ADD audio_url VARCHAR(255) DEFAULT NULL, ADD audio_language VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE profile ADD microphone_access BOOLEAN DEFAULT FALSE NOT NULL');
    }
}
