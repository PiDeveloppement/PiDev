<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408083747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement ADD original_quantity INT NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE equipement_type equipement_type VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE event CHANGE ticket_price ticket_price NUMERIC(10, 2) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE reservation_resource CHANGE quantity quantity INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE reservation_resource RENAME INDEX fk_res_salle TO IDX_21241602DC304035');
        $this->addSql('ALTER TABLE reservation_resource RENAME INDEX fk_res_equipement TO IDX_21241602806F0F5C');
        $this->addSql('ALTER TABLE salle ADD original_capacity INT NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE building building VARCHAR(50) NOT NULL, CHANGE floor floor INT NOT NULL, CHANGE status status VARCHAR(50) NOT NULL, CHANGE latitude latitude DOUBLE PRECISION DEFAULT NULL, CHANGE longitude longitude DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement DROP original_quantity, CHANGE name name VARCHAR(100) NOT NULL, CHANGE equipement_type equipement_type VARCHAR(100) DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT \'DISPONIBLE\'');
        $this->addSql('ALTER TABLE reservation_resource CHANGE quantity quantity INT DEFAULT 1');
        $this->addSql('ALTER TABLE reservation_resource RENAME INDEX idx_21241602dc304035 TO fk_res_salle');
        $this->addSql('ALTER TABLE reservation_resource RENAME INDEX idx_21241602806f0f5c TO fk_res_equipement');
        $this->addSql('ALTER TABLE event CHANGE ticket_price ticket_price NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE salle DROP original_capacity, CHANGE name name VARCHAR(100) NOT NULL, CHANGE building building VARCHAR(100) DEFAULT NULL, CHANGE floor floor INT DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT \'DISPONIBLE\', CHANGE latitude latitude DOUBLE PRECISION DEFAULT \'36.8602\', CHANGE longitude longitude DOUBLE PRECISION DEFAULT \'10.1905\'');
    }
}
