<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to fix NULL and invalid quantity values in equipement table
 */
final class Version20260408154000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix NULL and invalid quantity values in equipement table - set minimum to 1';
    }

    public function up(Schema $schema): void
    {
        // Fix all NULL quantities to 1
        $this->addSql('UPDATE equipement SET quantity = 1 WHERE quantity IS NULL OR quantity <= 0');
    }

    public function down(Schema $schema): void
    {
        // No need to revert - we're just fixing data integrity
    }
}
