<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fait d’EventFavorite la source unique des favoris et de leurs rappels.
 */
final class Version20260729220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reprend les favoris existants et ajoute le suivi des rappels à 24 heures.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_favorite ADD reminded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // Chaque ancienne liaison est reprise avec un rappel actif. Le filtre
        // protège un éventuel favori déjà présent dans la table métier.
        $this->addSql(
            'INSERT INTO event_favorite (reminder_active, added_at, reminded_at, event_user_id, event_id) '
            . 'SELECT TRUE, CURRENT_TIMESTAMP, NULL, legacy.user_id, legacy.event_id '
            . 'FROM user_event legacy '
            . 'WHERE NOT EXISTS ('
            . 'SELECT 1 FROM event_favorite favorite '
            . 'WHERE favorite.event_user_id = legacy.user_id AND favorite.event_id = legacy.event_id'
            . ')'
        );

        // La table historique n’est retirée qu’après la copie ci-dessus.
        $this->addSql('ALTER TABLE user_event DROP CONSTRAINT FK_D96CF1FF71F7E88B');
        $this->addSql('ALTER TABLE user_event DROP CONSTRAINT FK_D96CF1FFA76ED395');
        $this->addSql('DROP TABLE user_event');

        $this->addSql('ALTER TABLE event_favorite DROP CONSTRAINT FK_2E29670922397A3A');
        $this->addSql('ALTER TABLE event_favorite DROP CONSTRAINT FK_2E29670971F7E88B');
        $this->addSql('ALTER TABLE event_favorite ADD CONSTRAINT FK_2E29670922397A3A FOREIGN KEY (event_user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_favorite ADD CONSTRAINT FK_2E29670971F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EVENT_FAVORITE_USER_EVENT ON event_favorite (event_user_id, event_id)');
    }

    public function down(Schema $schema): void
    {
        // Le retour arrière reconstruit d’abord toutes les anciennes liaisons.
        $this->addSql('CREATE TABLE user_event (user_id INT NOT NULL, event_id INT NOT NULL, PRIMARY KEY (user_id, event_id))');
        $this->addSql('CREATE INDEX IDX_D96CF1FFA76ED395 ON user_event (user_id)');
        $this->addSql('CREATE INDEX IDX_D96CF1FF71F7E88B ON user_event (event_id)');
        $this->addSql('INSERT INTO user_event (user_id, event_id) SELECT event_user_id, event_id FROM event_favorite');
        $this->addSql('ALTER TABLE user_event ADD CONSTRAINT FK_D96CF1FFA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_event ADD CONSTRAINT FK_D96CF1FF71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('DROP INDEX UNIQ_EVENT_FAVORITE_USER_EVENT');
        $this->addSql('ALTER TABLE event_favorite DROP CONSTRAINT FK_2E29670922397A3A');
        $this->addSql('ALTER TABLE event_favorite DROP CONSTRAINT FK_2E29670971F7E88B');
        $this->addSql('ALTER TABLE event_favorite ADD CONSTRAINT FK_2E29670922397A3A FOREIGN KEY (event_user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_favorite ADD CONSTRAINT FK_2E29670971F7E88B FOREIGN KEY (event_id) REFERENCES event (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_favorite DROP reminded_at');
    }
}


