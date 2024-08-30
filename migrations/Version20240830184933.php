<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240830184933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE plan DROP total_credits, DROP shared_credits');
        $this->addSql('ALTER TABLE subscription DROP remaining_credits, DROP shared_credits');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE plan ADD total_credits INT NOT NULL, ADD shared_credits INT DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD remaining_credits INT NOT NULL, ADD shared_credits INT DEFAULT NULL');
    }
}
