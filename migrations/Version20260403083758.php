<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403083758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, category_id INT NOT NULL, created_by INT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, location VARCHAR(255) NOT NULL, gouvernorat VARCHAR(100) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, capacity INT NOT NULL, image_url VARCHAR(500) DEFAULT NULL, status VARCHAR(20) DEFAULT \'DRAFT\' NOT NULL, is_free TINYINT(1) DEFAULT 1 NOT NULL, ticket_price NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_3BAE0AA712469DE2 (category_id), INDEX IDX_3BAE0AA7DE12AB56 (created_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(50) DEFAULT NULL, color VARCHAR(20) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event_ticket (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, user_id INT NOT NULL, ticket_code VARCHAR(50) NOT NULL, qr_code LONGTEXT DEFAULT NULL, is_used TINYINT(1) DEFAULT 0 NOT NULL, used_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_A539DAF145CE25A0 (ticket_code), INDEX IDX_A539DAF171F7E88B (event_id), INDEX IDX_A539DAF1A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE feedbacks (id_feedback INT AUTO_INCREMENT NOT NULL, id_user INT DEFAULT NULL, id_question INT DEFAULT NULL, reponse_donnee VARCHAR(255) DEFAULT NULL, comments VARCHAR(255) DEFAULT NULL, etoiles INT DEFAULT 0, INDEX IDX_7E6C3F896B3CA4B (id_user), INDEX IDX_7E6C3F89E62CA5DB (id_question), PRIMARY KEY(id_feedback)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE password_reset_tokens (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token VARCHAR(255) NOT NULL, expiry_date DATETIME NOT NULL, used TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_3967A2165F37A13B (token), UNIQUE INDEX UNIQ_3967A216A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE questions (id_question INT AUTO_INCREMENT NOT NULL, id_event INT DEFAULT NULL, id_user INT DEFAULT NULL, texte VARCHAR(255) DEFAULT NULL, reponse VARCHAR(255) DEFAULT NULL, points INT DEFAULT 0, option1 VARCHAR(255) DEFAULT NULL, option2 VARCHAR(255) DEFAULT NULL, option3 VARCHAR(255) DEFAULT NULL, INDEX IDX_8ADC54D5D52B4B97 (id_event), INDEX IDX_8ADC54D56B3CA4B (id_user), PRIMARY KEY(id_question)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE role (Id_Role INT AUTO_INCREMENT NOT NULL, RoleName VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_57698A6AFE6E2EE6 (RoleName), PRIMARY KEY(Id_Role)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_model (Id_User INT AUTO_INCREMENT NOT NULL, First_Name VARCHAR(45) NOT NULL, Last_Name VARCHAR(45) NOT NULL, email VARCHAR(45) NOT NULL, faculte VARCHAR(45) NOT NULL, password VARCHAR(255) NOT NULL, Role_Id INT NOT NULL, phone VARCHAR(20) DEFAULT NULL, profile_picture_url VARCHAR(500) DEFAULT NULL, registration_date DATETIME DEFAULT NULL, bio LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_35578981E7927C74 (email), INDEX IDX_355789818C3A3F92 (Role_Id), PRIMARY KEY(Id_User)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA712469DE2 FOREIGN KEY (category_id) REFERENCES event_category (id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7DE12AB56 FOREIGN KEY (created_by) REFERENCES user_model (Id_User)');
        $this->addSql('ALTER TABLE event_ticket ADD CONSTRAINT FK_A539DAF171F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE event_ticket ADD CONSTRAINT FK_A539DAF1A76ED395 FOREIGN KEY (user_id) REFERENCES user_model (Id_User)');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT FK_7E6C3F896B3CA4B FOREIGN KEY (id_user) REFERENCES user_model (Id_User)');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT FK_7E6C3F89E62CA5DB FOREIGN KEY (id_question) REFERENCES questions (id_question)');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_3967A216A76ED395 FOREIGN KEY (user_id) REFERENCES user_model (Id_User)');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT FK_8ADC54D5D52B4B97 FOREIGN KEY (id_event) REFERENCES event (id)');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT FK_8ADC54D56B3CA4B FOREIGN KEY (id_user) REFERENCES user_model (Id_User)');
        $this->addSql('ALTER TABLE user_model ADD CONSTRAINT FK_355789818C3A3F92 FOREIGN KEY (Role_Id) REFERENCES role (Id_Role)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA712469DE2');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7DE12AB56');
        $this->addSql('ALTER TABLE event_ticket DROP FOREIGN KEY FK_A539DAF171F7E88B');
        $this->addSql('ALTER TABLE event_ticket DROP FOREIGN KEY FK_A539DAF1A76ED395');
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY FK_7E6C3F896B3CA4B');
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY FK_7E6C3F89E62CA5DB');
        $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY FK_3967A216A76ED395');
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY FK_8ADC54D5D52B4B97');
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY FK_8ADC54D56B3CA4B');
        $this->addSql('ALTER TABLE user_model DROP FOREIGN KEY FK_355789818C3A3F92');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE event_category');
        $this->addSql('DROP TABLE event_ticket');
        $this->addSql('DROP TABLE feedbacks');
        $this->addSql('DROP TABLE password_reset_tokens');
        $this->addSql('DROP TABLE questions');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE user_model');
    }
}
