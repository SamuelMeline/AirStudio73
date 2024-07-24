<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240724210712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subscription_course (id INT AUTO_INCREMENT NOT NULL, subscription_id INT NOT NULL, course_id INT NOT NULL, remaining_credits INT NOT NULL, INDEX IDX_DF68773E9A1887DC (subscription_id), INDEX IDX_DF68773E591CC992 (course_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE subscription_course ADD CONSTRAINT FK_DF68773E9A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id)');
        $this->addSql('ALTER TABLE subscription_course ADD CONSTRAINT FK_DF68773E591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subscription_course DROP FOREIGN KEY FK_DF68773E9A1887DC');
        $this->addSql('ALTER TABLE subscription_course DROP FOREIGN KEY FK_DF68773E591CC992');
        $this->addSql('DROP TABLE subscription_course');
    }
}
