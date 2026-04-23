<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260423110937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE quiz_sessions (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, event_id INT DEFAULT NULL, session_token VARCHAR(255) NOT NULL, recaptcha_verified TINYINT(1) DEFAULT 0 NOT NULL, recaptcha_token VARCHAR(512) DEFAULT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_4CA46456844A19ED (session_token), INDEX IDX_4CA46456A76ED395 (user_id), INDEX IDX_4CA4645671F7E88B (event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE quiz_sessions ADD CONSTRAINT FK_4CA46456A76ED395 FOREIGN KEY (user_id) REFERENCES user_model (Id_User) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_sessions ADD CONSTRAINT FK_4CA4645671F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE password_reset_tokens DROP INDEX UNIQ_3967A216A76ED395, ADD INDEX IDX_3967A216A76ED395 (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE quiz_sessions DROP FOREIGN KEY FK_4CA46456A76ED395');
        $this->addSql('ALTER TABLE quiz_sessions DROP FOREIGN KEY FK_4CA4645671F7E88B');
        $this->addSql('DROP TABLE quiz_sessions');
        $this->addSql('ALTER TABLE password_reset_tokens DROP INDEX IDX_3967A216A76ED395, ADD UNIQUE INDEX UNIQ_3967A216A76ED395 (user_id)');
    }
}
