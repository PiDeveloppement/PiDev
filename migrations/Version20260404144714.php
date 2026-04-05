<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404144714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des foreign keys pour event et event_ticket sans modifications destructives';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_EVENT_CATEGORY FOREIGN KEY (category_id) REFERENCES event_category (id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_EVENT_CREATED_BY_USER FOREIGN KEY (created_by) REFERENCES user_model (Id_User)');
        $this->addSql('ALTER TABLE event_ticket ADD CONSTRAINT FK_EVENT_TICKET_EVENT FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE event_ticket ADD CONSTRAINT FK_EVENT_TICKET_USER FOREIGN KEY (user_id) REFERENCES user_model (Id_User)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_EVENT_CATEGORY');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_EVENT_CREATED_BY_USER');
        $this->addSql('ALTER TABLE event_ticket DROP FOREIGN KEY FK_EVENT_TICKET_EVENT');
        $this->addSql('ALTER TABLE event_ticket DROP FOREIGN KEY FK_EVENT_TICKET_USER');
    }
}
