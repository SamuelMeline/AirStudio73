<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240704195037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking ADD user_name VARCHAR(255) NOT NULL, DROP recurrence, DROP duration');
        $this->addSql('ALTER TABLE course DROP recurrence_day');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking ADD recurrence VARCHAR(255) DEFAULT NULL, ADD duration INT DEFAULT NULL, DROP user_name');
        $this->addSql('ALTER TABLE course ADD recurrence_day INT DEFAULT NULL');
    }
}
