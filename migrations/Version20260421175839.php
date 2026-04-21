<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260421175839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feedbacks ADD original_comments VARCHAR(255) DEFAULT NULL, ADD cleaned_comments VARCHAR(255) DEFAULT NULL, ADD toxicity_level VARCHAR(20) DEFAULT NULL, ADD detected_words JSON DEFAULT NULL, ADD is_moderated TINYINT(1) DEFAULT 0 NOT NULL, ADD is_approved TINYINT(1) DEFAULT 0 NOT NULL, ADD moderated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feedbacks DROP original_comments, DROP cleaned_comments, DROP toxicity_level, DROP detected_words, DROP is_moderated, DROP is_approved, DROP moderated_at');
    }
}
