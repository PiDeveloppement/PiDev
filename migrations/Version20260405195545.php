<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405195545 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7DE12AB56');
        $this->addSql('ALTER TABLE event CHANGE ticket_price ticket_price DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7DE12AB56 FOREIGN KEY (created_by) REFERENCES user_model (Id_User)');
        $this->addSql('ALTER TABLE event_ticket DROP FOREIGN KEY FK_A539DAF171F7E88B');
        $this->addSql('ALTER TABLE event_ticket ADD CONSTRAINT FK_A539DAF171F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY feedbacks_ibfk_2');
        $this->addSql('ALTER TABLE feedbacks ADD id_event INT DEFAULT NULL');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT FK_7E6C3F89D52B4B97 FOREIGN KEY (id_event) REFERENCES event (id)');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT FK_7E6C3F89E62CA5DB FOREIGN KEY (id_question) REFERENCES questions (id_question)');
        $this->addSql('CREATE INDEX IDX_7E6C3F89D52B4B97 ON feedbacks (id_event)');
        $this->addSql('ALTER TABLE password_reset_tokens DROP INDEX IDX_3967A216A76ED395, ADD UNIQUE INDEX UNIQ_3967A216A76ED395 (user_id)');
        $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY password_reset_tokens_ibfk_1');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_3967A216A76ED395 FOREIGN KEY (user_id) REFERENCES user_model (Id_User)');
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY FK_8ADC54D5D52B4B97');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT FK_8ADC54D5D52B4B97 FOREIGN KEY (id_event) REFERENCES event (id)');
        $this->addSql('ALTER TABLE user_model RENAME INDEX fk_355789818c3a3f92 TO IDX_355789818C3A3F92');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE password_reset_tokens DROP INDEX UNIQ_3967A216A76ED395, ADD INDEX IDX_3967A216A76ED395 (user_id)');
        $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY FK_3967A216A76ED395');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT password_reset_tokens_ibfk_1 FOREIGN KEY (user_id) REFERENCES user_model (Id_User) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_ticket DROP FOREIGN KEY FK_A539DAF171F7E88B');
        $this->addSql('ALTER TABLE event_ticket ADD CONSTRAINT FK_A539DAF171F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7DE12AB56');
        $this->addSql('ALTER TABLE event CHANGE ticket_price ticket_price NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7DE12AB56 FOREIGN KEY (created_by) REFERENCES user_model (Id_User) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY FK_8ADC54D5D52B4B97');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT FK_8ADC54D5D52B4B97 FOREIGN KEY (id_event) REFERENCES event (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_model RENAME INDEX idx_355789818c3a3f92 TO FK_355789818C3A3F92');
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY FK_7E6C3F89D52B4B97');
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY FK_7E6C3F89E62CA5DB');
        $this->addSql('DROP INDEX IDX_7E6C3F89D52B4B97 ON feedbacks');
        $this->addSql('ALTER TABLE feedbacks DROP id_event');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT feedbacks_ibfk_2 FOREIGN KEY (id_question) REFERENCES questions (id_question) ON UPDATE NO ACTION ON DELETE CASCADE');
    }
}
