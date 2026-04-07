<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407110435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_resource CHANGE reservation_date_start_time reservation_date_start_time DATE NOT NULL');
        $this->addSql('ALTER TABLE reservation_resource CHANGE end_time end_time DATE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_resource CHANGE reservation_date_start_time reservation_date_start_time DATETIME NOT NULL');
        $this->addSql('ALTER TABLE reservation_resource CHANGE end_time end_time DATETIME NOT NULL');
    }
}
