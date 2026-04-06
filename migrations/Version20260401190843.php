<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create password_reset_tokens table only
 */
final class Version20260401190843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create password_reset_tokens table';
    }

    public function up(Schema $schema): void
    {
        // Create password_reset_tokens table only - NO changes to other tables
        $this->addSql('CREATE TABLE IF NOT EXISTS password_reset_tokens (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token VARCHAR(255) NOT NULL, expiry_date DATETIME NOT NULL, used TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_3967A2165F37A13B (token), UNIQUE INDEX UNIQ_3967A216A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // Drop password_reset_tokens table only
        $this->addSql('DROP TABLE IF EXISTS password_reset_tokens');
    }
}
