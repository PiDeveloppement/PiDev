<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409201003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY FK_7E6C3F896B3CA4B');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT FK_7E6C3F896B3CA4B FOREIGN KEY (id_user) REFERENCES user_model (Id_User) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY FK_7E6C3F896B3CA4B');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT FK_7E6C3F896B3CA4B FOREIGN KEY (id_user) REFERENCES user_model (Id_User) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
