<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240727101344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE6BA30B2E FOREIGN KEY (subscription_course_id) REFERENCES subscription_course (id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE6BA30B2E ON booking (subscription_course_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE6BA30B2E');
        $this->addSql('DROP INDEX IDX_E00CEDDE6BA30B2E ON booking');
    }
}
