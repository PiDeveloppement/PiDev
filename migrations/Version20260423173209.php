<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260423173209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Créer les tables d\'audit et d\'historique';
    }

    public function up(Schema $schema): void
    {
        // Créer d'abord la table audit_logs si elle n'existe pas
        $this->addSql('CREATE TABLE IF NOT EXISTS audit_logs (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, action VARCHAR(20) NOT NULL, resource_type VARCHAR(50) NOT NULL, resource_id INT NOT NULL, resource_name VARCHAR(255) NOT NULL, old_values LONGTEXT DEFAULT NULL, new_values LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, ip_address VARCHAR(255) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, INDEX IDX_D62F2858A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Créer la table audit_associations pour data-dog/audit-bundle
        $this->addSql('CREATE TABLE IF NOT EXISTS audit_associations (id INT AUTO_INCREMENT NOT NULL, typ VARCHAR(255) NOT NULL, tbl VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, fk INT NOT NULL, class VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Renommer notre table audit_logs en historique_logs
        $this->addSql('DROP TABLE IF EXISTS historique_logs');
        $this->addSql('RENAME TABLE audit_logs TO historique_logs');
        
        // Ajouter des index pour la performance
        $this->addSql('CREATE INDEX IDX_HISTORIQUE_ACTION ON historique_logs (action)');
        $this->addSql('CREATE INDEX IDX_HISTORIQUE_RESOURCE_TYPE ON historique_logs (resource_type)');
        $this->addSql('CREATE INDEX IDX_HISTORIQUE_CREATED_AT ON historique_logs (created_at)');
    }

    public function down(Schema $schema): void
    {
        // Supprimer les index ajoutés
        $this->addSql('DROP INDEX IDX_HISTORIQUE_ACTION ON historique_logs');
        $this->addSql('DROP INDEX IDX_HISTORIQUE_RESOURCE_TYPE ON historique_logs');
        $this->addSql('DROP INDEX IDX_HISTORIQUE_CREATED_AT ON historique_logs');
        
        // Renommer la table historique_logs en audit_logs
        $this->addSql('RENAME TABLE historique_logs TO audit_logs');
        
        // Supprimer la table audit_associations
        $this->addSql('DROP TABLE audit_associations');
    }
}
