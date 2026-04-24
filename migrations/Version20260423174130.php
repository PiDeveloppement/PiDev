<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260423174130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recréer la table historique_logs pour le système d\'audit';
    }

    public function up(Schema $schema): void
    {
        // Recréer la table historique_logs correctement
        $this->addSql('DROP TABLE IF EXISTS historique_logs');
        $this->addSql('CREATE TABLE historique_logs (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, action VARCHAR(20) NOT NULL, resource_type VARCHAR(50) NOT NULL, resource_id INT NOT NULL, resource_name VARCHAR(255) NOT NULL, old_values LONGTEXT DEFAULT NULL, new_values LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, ip_address VARCHAR(255) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, INDEX IDX_D62F2858A76ED395 (user_id), INDEX IDX_HISTORIQUE_ACTION (action), INDEX IDX_HISTORIQUE_RESOURCE_TYPE (resource_type), INDEX IDX_HISTORIQUE_CREATED_AT (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Ajouter la contrainte de clé étrangère
        $this->addSql('ALTER TABLE historique_logs ADD CONSTRAINT FK_HISTORIQUE_USER FOREIGN KEY (user_id) REFERENCES user_model (Id_User) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE historique_logs (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, action VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, resource_type VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, resource_id INT NOT NULL, resource_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, old_values LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, new_values LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, ip_address VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, user_agent VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_D62F2858A76ED395 (user_id), INDEX IDX_HISTORIQUE_ACTION (action), INDEX IDX_HISTORIQUE_RESOURCE_TYPE (resource_type), INDEX IDX_HISTORIQUE_CREATED_AT (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE historique_logs ADD CONSTRAINT FK_HISTORIQUE_USER FOREIGN KEY (user_id) REFERENCES user_model (Id_User) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('DROP INDEX IDX_969DC4BA81E660E ON audit_associations');
        $this->addSql('ALTER TABLE audit_associations CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE typ typ VARCHAR(255) NOT NULL, CHANGE tbl tbl VARCHAR(255) NOT NULL, CHANGE label label VARCHAR(255) NOT NULL, CHANGE fk fk INT NOT NULL');
        $this->addSql('ALTER TABLE audit_logs DROP FOREIGN KEY FK_D62F2858953C1C61');
        $this->addSql('ALTER TABLE audit_logs DROP FOREIGN KEY FK_D62F2858158E0B66');
        $this->addSql('ALTER TABLE audit_logs DROP FOREIGN KEY FK_D62F28588C082A2E');
    }
}
