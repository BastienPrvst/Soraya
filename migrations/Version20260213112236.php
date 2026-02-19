<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213112236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY `FK_F5299398C0E3B53E`');
        $this->addSql('DROP INDEX IDX_F5299398C0E3B53E ON `order`');
        $this->addSql('ALTER TABLE `order` ADD email VARCHAR(255) NOT NULL, CHANGE delivery_adress_id delivery_address_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398EBF23851 FOREIGN KEY (delivery_address_id) REFERENCES address (id)');
        $this->addSql('CREATE INDEX IDX_F5299398EBF23851 ON `order` (delivery_address_id)');
        $this->addSql('DROP TABLE adress');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE adress (id INT AUTO_INCREMENT NOT NULL, city VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, zipcode INT NOT NULL, street1 VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, street2 VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, is_active TINYINT NOT NULL, user_id INT DEFAULT NULL, country VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, INDEX IDX_5CECC7BEA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398EBF23851');
        $this->addSql('DROP INDEX IDX_F5299398EBF23851 ON `order`');
        $this->addSql('ALTER TABLE `order` DROP email, CHANGE delivery_address_id delivery_adress_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT `FK_F5299398C0E3B53E` FOREIGN KEY (delivery_adress_id) REFERENCES adress (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_F5299398C0E3B53E ON `order` (delivery_adress_id)');
    }
}
