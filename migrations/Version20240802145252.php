<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240802145252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course_details DROP FOREIGN KEY FK_7AE50E6664F849A1');
        $this->addSql('DROP INDEX IDX_7AE50E6664F849A1 ON course_details');
        $this->addSql('ALTER TABLE course_details DROP course_details_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course_details ADD course_details_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE course_details ADD CONSTRAINT FK_7AE50E6664F849A1 FOREIGN KEY (course_details_id) REFERENCES course_details (id)');
        $this->addSql('CREATE INDEX IDX_7AE50E6664F849A1 ON course_details (course_details_id)');
    }
}
