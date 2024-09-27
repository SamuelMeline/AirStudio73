<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240927071429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE review (id INT AUTO_INCREMENT NOT NULL, course_details_id INT NOT NULL, user_id INT NOT NULL, comment LONGTEXT NOT NULL, rating INT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_794381C664F849A1 (course_details_id), INDEX IDX_794381C6A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C664F849A1 FOREIGN KEY (course_details_id) REFERENCES course_details (id)');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE coach ADD description LONGTEXT DEFAULT NULL, ADD second_description LONGTEXT DEFAULT NULL, ADD third_description LONGTEXT DEFAULT NULL, CHANGE biography presentation LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE course_details ADD benefits LONGTEXT DEFAULT NULL, ADD photobenefits VARCHAR(255) DEFAULT NULL, ADD second_benefits LONGTEXT DEFAULT NULL, DROP default_capacity');
        $this->addSql('ALTER TABLE plan ADD subscription_type VARCHAR(255) DEFAULT NULL, CHANGE max_payments stripe_product_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE plan_course ADD price_per_credit DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE subscription ADD payment_installments INT DEFAULT NULL, ADD stripe_product_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C664F849A1');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C6A76ED395');
        $this->addSql('DROP TABLE review');
        $this->addSql('ALTER TABLE coach ADD biography LONGTEXT DEFAULT NULL, DROP presentation, DROP description, DROP second_description, DROP third_description');
        $this->addSql('ALTER TABLE course_details ADD default_capacity INT NOT NULL, DROP benefits, DROP photobenefits, DROP second_benefits');
        $this->addSql('ALTER TABLE plan ADD max_payments VARCHAR(255) DEFAULT NULL, DROP stripe_product_id, DROP subscription_type');
        $this->addSql('ALTER TABLE plan_course DROP price_per_credit');
        $this->addSql('ALTER TABLE subscription DROP payment_installments, DROP stripe_product_id');
    }
}
