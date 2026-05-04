<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour créer la table historique_logs
 */
final class Version20260424040224 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create historique_logs table for audit trail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE historique_logs (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            action VARCHAR(20) NOT NULL,
            resource_type VARCHAR(50) NOT NULL,
            resource_id INT NOT NULL,
            resource_name VARCHAR(255) NOT NULL,
            old_values TEXT DEFAULT NULL,
            new_values TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ip_address VARCHAR(255) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            INDEX IDX_HISTORIQUE_LOGS_USER_ID (user_id),
            INDEX IDX_HISTORIQUE_LOGS_ACTION (action),
            INDEX IDX_HISTORIQUE_LOGS_RESOURCE_TYPE (resource_type),
            INDEX IDX_HISTORIQUE_LOGS_RESOURCE_ID (resource_id),
            INDEX IDX_HISTORIQUE_LOGS_CREATED_AT (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE historique_logs');
    }
}
