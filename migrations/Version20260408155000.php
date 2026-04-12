<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to fix equipment status values - normalize EN_PANNE to MAINTENANCE
 */
final class Version20260408155000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize equipment status values: EN_PANNE -> MAINTENANCE';
    }

    public function up(Schema $schema): void
    {
        // Convert EN_PANNE to MAINTENANCE
        $this->addSql("UPDATE equipement SET status = 'MAINTENANCE' WHERE status = 'EN_PANNE'");
        // Ensure no null statuses
        $this->addSql("UPDATE equipement SET status = 'DISPONIBLE' WHERE status IS NULL");
    }

    public function down(Schema $schema): void
    {
        // No need to revert - we're just fixing data consistency
    }
}
