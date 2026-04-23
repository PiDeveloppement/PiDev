<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout de la table notification pour le systeme de notifications participant.
 */
final class Version20260423102419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creation de la table notification';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            event_id INT DEFAULT NULL,
            type VARCHAR(30) NOT NULL,
            title VARCHAR(150) NOT NULL,
            message LONGTEXT NOT NULL,
            icon VARCHAR(10) DEFAULT NULL,
            is_read TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_BF5476CAA76ED395 (user_id),
            INDEX IDX_BF5476CA71F7E88B (event_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES user_model (Id_User) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA71F7E88B');
        $this->addSql('DROP TABLE notification');
    }
}