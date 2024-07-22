<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240722121752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking ADD use_credits TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE course CHANGE price price INT NOT NULL');
        $this->addSql('ALTER TABLE user ADD credits INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP use_credits');
        $this->addSql('ALTER TABLE course CHANGE price price NUMERIC(10, 0) NOT NULL');
        $this->addSql('ALTER TABLE `user` DROP credits');
    }
}
