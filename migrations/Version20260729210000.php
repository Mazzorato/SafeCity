<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Relie facultativement une photo au commentaire dans lequel elle a été ajoutée.
 */
final class Version20260729210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les photos facultatives aux commentaires de signalement.';
    }

    public function up(Schema $schema): void
    {
        // La colonne nullable préserve toutes les photos créées avant ce lot.
        $this->addSql('ALTER TABLE photo ADD comment_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B78418F8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_14B78418F8697D13 ON photo (comment_id)');
    }

    public function down(Schema $schema): void
    {
        // Le retour arrière retire seulement le lien, jamais les fichiers photo.
        $this->addSql('ALTER TABLE photo DROP CONSTRAINT FK_14B78418F8697D13');
        $this->addSql('DROP INDEX IDX_14B78418F8697D13');
        $this->addSql('ALTER TABLE photo DROP comment_id');
    }
}
