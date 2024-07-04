<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240704200907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE4E3F42C9');
        $this->addSql('ALTER TABLE course_instance DROP FOREIGN KEY FK_EB84DC88591CC992');
        $this->addSql('DROP TABLE course_instance');
        $this->addSql('DROP INDEX IDX_E00CEDDE4E3F42C9 ON booking');
        $this->addSql('ALTER TABLE booking DROP start_date, CHANGE course_instance_id course_id INT NOT NULL');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE591CC992 ON booking (course_id)');
        $this->addSql('ALTER TABLE course DROP description');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE course_instance (id INT AUTO_INCREMENT NOT NULL, course_id INT NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME NOT NULL, capacity INT NOT NULL, INDEX IDX_EB84DC88591CC992 (course_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE course_instance ADD CONSTRAINT FK_EB84DC88591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE591CC992');
        $this->addSql('DROP INDEX IDX_E00CEDDE591CC992 ON booking');
        $this->addSql('ALTER TABLE booking ADD start_date DATETIME NOT NULL, CHANGE course_id course_instance_id INT NOT NULL');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE4E3F42C9 FOREIGN KEY (course_instance_id) REFERENCES course_instance (id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE4E3F42C9 ON booking (course_instance_id)');
        $this->addSql('ALTER TABLE course ADD description LONGTEXT DEFAULT NULL');
    }
}
