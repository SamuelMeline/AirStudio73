<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240724203336 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plan_course (id INT AUTO_INCREMENT NOT NULL, plan_id INT NOT NULL, course_id INT NOT NULL, credits INT NOT NULL, INDEX IDX_A2C677CBE899029B (plan_id), INDEX IDX_A2C677CB591CC992 (course_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE plan_course ADD CONSTRAINT FK_A2C677CBE899029B FOREIGN KEY (plan_id) REFERENCES plan (id)');
        $this->addSql('ALTER TABLE plan_course ADD CONSTRAINT FK_A2C677CB591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('ALTER TABLE plan DROP FOREIGN KEY FK_DD5A5B7D591CC992');
        $this->addSql('DROP INDEX IDX_DD5A5B7D591CC992 ON plan');
        $this->addSql('ALTER TABLE plan DROP course_id, DROP courses');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE plan_course DROP FOREIGN KEY FK_A2C677CBE899029B');
        $this->addSql('ALTER TABLE plan_course DROP FOREIGN KEY FK_A2C677CB591CC992');
        $this->addSql('DROP TABLE plan_course');
        $this->addSql('ALTER TABLE plan ADD course_id INT NOT NULL, ADD courses INT NOT NULL');
        $this->addSql('ALTER TABLE plan ADD CONSTRAINT FK_DD5A5B7D591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('CREATE INDEX IDX_DD5A5B7D591CC992 ON plan (course_id)');
    }
}
