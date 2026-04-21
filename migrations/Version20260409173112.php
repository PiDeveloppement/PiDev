<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409173112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event CHANGE ticket_price ticket_price DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY FK_7E6C3F89D52B4B97');
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY FK_7E6C3F89E62CA5DB');
        $this->addSql('DROP INDEX IDX_7E6C3F89D52B4B97 ON feedbacks');
        $this->addSql('ALTER TABLE feedbacks DROP id_event, CHANGE id_user id_user INT DEFAULT 45');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT FK_7E6C3F89E62CA5DB FOREIGN KEY (id_question) REFERENCES questions (id_question) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY FK_8ADC54D56B3CA4B');
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY FK_8ADC54D5D52B4B97');
        $this->addSql('DROP INDEX IDX_8ADC54D56B3CA4B ON questions');
        $this->addSql('ALTER TABLE questions DROP id_user, CHANGE points points INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT FK_8ADC54D5D52B4B97 FOREIGN KEY (id_event) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE salle DROP original_capacity');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event CHANGE ticket_price ticket_price NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY FK_7E6C3F89E62CA5DB');
        $this->addSql('ALTER TABLE feedbacks ADD id_event INT DEFAULT NULL, CHANGE id_user id_user INT DEFAULT NULL');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT FK_7E6C3F89D52B4B97 FOREIGN KEY (id_event) REFERENCES event (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT FK_7E6C3F89E62CA5DB FOREIGN KEY (id_question) REFERENCES questions (id_question) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_7E6C3F89D52B4B97 ON feedbacks (id_event)');
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY FK_8ADC54D5D52B4B97');
        $this->addSql('ALTER TABLE questions ADD id_user INT DEFAULT NULL, CHANGE points points INT DEFAULT 0');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT FK_8ADC54D56B3CA4B FOREIGN KEY (id_user) REFERENCES user_model (Id_User) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT FK_8ADC54D5D52B4B97 FOREIGN KEY (id_event) REFERENCES event (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_8ADC54D56B3CA4B ON questions (id_user)');
        $this->addSql('ALTER TABLE salle ADD original_capacity INT NOT NULL');
    }
}
