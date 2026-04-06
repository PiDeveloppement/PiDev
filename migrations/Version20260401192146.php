<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260401192146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event CHANGE ticket_price ticket_price NUMERIC(10, 2) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7DE12AB56 FOREIGN KEY (created_by) REFERENCES user_model (Id_User)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7DE12AB56 ON event (created_by)');
        $this->addSql('ALTER TABLE event RENAME INDEX fk_3bae0aa712469de2 TO IDX_3BAE0AA712469DE2');
        $this->addSql('ALTER TABLE event_category CHANGE description description LONGTEXT DEFAULT NULL, CHANGE color color VARCHAR(20) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE icon icon VARCHAR(50) DEFAULT NULL, CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE event_ticket DROP FOREIGN KEY event_ticket_ibfk_1');
        $this->addSql('ALTER TABLE event_ticket DROP FOREIGN KEY event_ticket_ibfk_2');
        $this->addSql('DROP INDEX idx_code ON event_ticket');
        $this->addSql('ALTER TABLE event_ticket CHANGE qr_code qr_code LONGTEXT DEFAULT NULL, CHANGE is_used is_used TINYINT(1) DEFAULT 0 NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE event_ticket ADD CONSTRAINT FK_A539DAF171F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE event_ticket ADD CONSTRAINT FK_A539DAF1A76ED395 FOREIGN KEY (user_id) REFERENCES user_model (Id_User)');
        $this->addSql('ALTER TABLE event_ticket RENAME INDEX ticket_code TO UNIQ_A539DAF145CE25A0');
        $this->addSql('ALTER TABLE event_ticket RENAME INDEX idx_event TO IDX_A539DAF171F7E88B');
        $this->addSql('ALTER TABLE event_ticket RENAME INDEX idx_user TO IDX_A539DAF1A76ED395');
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY fk_feedbacks_event');
        $this->addSql('DROP INDEX fk_feedbacks_event ON feedbacks');
        $this->addSql('ALTER TABLE feedbacks DROP id_event');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT FK_7E6C3F896B3CA4B FOREIGN KEY (id_user) REFERENCES user_model (Id_User)');
        $this->addSql('ALTER TABLE feedbacks RENAME INDEX id_user TO IDX_7E6C3F896B3CA4B');
        $this->addSql('ALTER TABLE feedbacks RENAME INDEX id_question TO IDX_7E6C3F89E62CA5DB');
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY fk_questions_event');
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY fk_questions_user');
        $this->addSql('ALTER TABLE questions ADD texte VARCHAR(255) DEFAULT NULL, ADD reponse VARCHAR(255) DEFAULT NULL, DROP texte_question, DROP bonne_reponse, CHANGE id_user id_user INT DEFAULT NULL, CHANGE points points INT DEFAULT 0, CHANGE option1 option1 VARCHAR(255) DEFAULT NULL, CHANGE option2 option2 VARCHAR(255) DEFAULT NULL, CHANGE option3 option3 VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT FK_8ADC54D5D52B4B97 FOREIGN KEY (id_event) REFERENCES event (id)');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT FK_8ADC54D56B3CA4B FOREIGN KEY (id_user) REFERENCES user_model (Id_User)');
        $this->addSql('ALTER TABLE questions RENAME INDEX fk_questions_event TO IDX_8ADC54D5D52B4B97');
        $this->addSql('ALTER TABLE questions RENAME INDEX fk_questions_user TO IDX_8ADC54D56B3CA4B');
        $this->addSql('ALTER TABLE role CHANGE RoleName RoleName VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_57698A6AFE6E2EE6 ON role (RoleName)');
        $this->addSql('ALTER TABLE user_model DROP FOREIGN KEY fk_user_role');
        $this->addSql('ALTER TABLE user_model CHANGE Password password VARCHAR(255) NOT NULL, CHANGE registration_date registration_date DATETIME DEFAULT NULL, CHANGE bio bio LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_model ADD CONSTRAINT FK_355789818C3A3F92 FOREIGN KEY (Role_Id) REFERENCES role (Id_Role)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_35578981E7927C74 ON user_model (email)');
        $this->addSql('ALTER TABLE user_model RENAME INDEX fk_user_role TO IDX_355789818C3A3F92');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_ticket DROP FOREIGN KEY FK_A539DAF171F7E88B');
        $this->addSql('ALTER TABLE event_ticket DROP FOREIGN KEY FK_A539DAF1A76ED395');
        $this->addSql('ALTER TABLE event_ticket CHANGE qr_code qr_code TEXT DEFAULT NULL, CHANGE is_used is_used TINYINT(1) DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE event_ticket ADD CONSTRAINT event_ticket_ibfk_1 FOREIGN KEY (event_id) REFERENCES event (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_ticket ADD CONSTRAINT event_ticket_ibfk_2 FOREIGN KEY (user_id) REFERENCES user_model (Id_User) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_code ON event_ticket (ticket_code)');
        $this->addSql('ALTER TABLE event_ticket RENAME INDEX uniq_a539daf145ce25a0 TO ticket_code');
        $this->addSql('ALTER TABLE event_ticket RENAME INDEX idx_a539daf171f7e88b TO idx_event');
        $this->addSql('ALTER TABLE event_ticket RENAME INDEX idx_a539daf1a76ed395 TO idx_user');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7DE12AB56');
        $this->addSql('DROP INDEX IDX_3BAE0AA7DE12AB56 ON event');
        $this->addSql('ALTER TABLE event CHANGE ticket_price ticket_price NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE event RENAME INDEX idx_3bae0aa712469de2 TO FK_3BAE0AA712469DE2');
        $this->addSql('DROP INDEX UNIQ_57698A6AFE6E2EE6 ON role');
        $this->addSql('ALTER TABLE role CHANGE RoleName RoleName VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY FK_8ADC54D5D52B4B97');
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY FK_8ADC54D56B3CA4B');
        $this->addSql('ALTER TABLE questions ADD texte_question TEXT NOT NULL, ADD bonne_reponse VARCHAR(255) NOT NULL, DROP texte, DROP reponse, CHANGE id_user id_user INT NOT NULL, CHANGE points points INT DEFAULT 1, CHANGE option1 option1 VARCHAR(255) NOT NULL, CHANGE option2 option2 VARCHAR(255) NOT NULL, CHANGE option3 option3 VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT fk_questions_event FOREIGN KEY (id_event) REFERENCES event (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT fk_questions_user FOREIGN KEY (id_user) REFERENCES user_model (Id_User) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE questions RENAME INDEX idx_8adc54d5d52b4b97 TO fk_questions_event');
        $this->addSql('ALTER TABLE questions RENAME INDEX idx_8adc54d56b3ca4b TO fk_questions_user');
        $this->addSql('ALTER TABLE user_model DROP FOREIGN KEY FK_355789818C3A3F92');
        $this->addSql('DROP INDEX UNIQ_35578981E7927C74 ON user_model');
        $this->addSql('ALTER TABLE user_model CHANGE password Password VARCHAR(45) NOT NULL, CHANGE registration_date registration_date DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE bio bio TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_model ADD CONSTRAINT fk_user_role FOREIGN KEY (Role_Id) REFERENCES role (Id_Role) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_model RENAME INDEX idx_355789818c3a3f92 TO fk_user_role');
        $this->addSql('ALTER TABLE event_category CHANGE description description TEXT DEFAULT NULL, CHANGE icon icon VARCHAR(50) DEFAULT \'?\', CHANGE color color VARCHAR(20) DEFAULT \'#3b82f6\', CHANGE is_active is_active TINYINT(1) DEFAULT 1, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY FK_7E6C3F896B3CA4B');
        $this->addSql('ALTER TABLE feedbacks ADD id_event INT DEFAULT NULL');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT fk_feedbacks_event FOREIGN KEY (id_event) REFERENCES event (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE INDEX fk_feedbacks_event ON feedbacks (id_event)');
        $this->addSql('ALTER TABLE feedbacks RENAME INDEX idx_7e6c3f896b3ca4b TO id_user');
        $this->addSql('ALTER TABLE feedbacks RENAME INDEX idx_7e6c3f89e62ca5db TO id_question');
    }
}
