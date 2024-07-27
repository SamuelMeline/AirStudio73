<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240727121141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking ADD parent_booking_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE44739830 FOREIGN KEY (parent_booking_id) REFERENCES booking (id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE44739830 ON booking (parent_booking_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE44739830');
        $this->addSql('DROP INDEX IDX_E00CEDDE44739830 ON booking');
        $this->addSql('ALTER TABLE booking DROP parent_booking_id');
    }
}
