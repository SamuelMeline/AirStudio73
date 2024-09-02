<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240831021957 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course ADD is_canceled TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE plan ADD start_date DATE DEFAULT NULL, ADD end_date DATE DEFAULT NULL, DROP duration');
        $this->addSql('ALTER TABLE subscription CHANGE purchase_date purchase_date DATE NOT NULL, CHANGE expiry_date expiry_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD address VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course DROP is_canceled');
        $this->addSql('ALTER TABLE plan ADD duration VARCHAR(255) NOT NULL, DROP start_date, DROP end_date');
        $this->addSql('ALTER TABLE subscription CHANGE purchase_date purchase_date DATETIME NOT NULL, CHANGE expiry_date expiry_date DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user DROP address');
    }
}
