<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409201259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE questions ADD id_user INT DEFAULT NULL');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT FK_8ADC54D56B3CA4B FOREIGN KEY (id_user) REFERENCES user_model (Id_User) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_8ADC54D56B3CA4B ON questions (id_user)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY FK_8ADC54D56B3CA4B');
        $this->addSql('DROP INDEX IDX_8ADC54D56B3CA4B ON questions');
        $this->addSql('ALTER TABLE questions DROP id_user');
    }
}
