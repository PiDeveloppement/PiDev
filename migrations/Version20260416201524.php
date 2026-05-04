<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260416201524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_resource ADD event_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_resource ADD CONSTRAINT FK_2124160271F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_2124160271F7E88B ON reservation_resource (event_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_resource DROP FOREIGN KEY FK_2124160271F7E88B');
        $this->addSql('DROP INDEX IDX_2124160271F7E88B ON reservation_resource');
        $this->addSql('ALTER TABLE reservation_resource DROP event_id');
    }
}