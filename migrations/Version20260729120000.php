<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime du schéma la dernière donnée persistée propre à Google OAuth.
 */
final class Version20260729120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime le champ Google OAuth devenu inutile sur les comptes utilisateurs.';
    }

    public function up(Schema $schema): void
    {
        // Le contrôle préalable a confirmé qu'aucun compte ne contient d'identifiant Google.
        $this->addSql('ALTER TABLE "user" DROP google_id');
    }

    public function down(Schema $schema): void
    {
        // Le retour arrière restaure uniquement la colonne historique, sans réactiver OAuth.
        $this->addSql('ALTER TABLE "user" ADD google_id VARCHAR(255) DEFAULT NULL');
    }
}
