<?php

namespace aibianchi\ExactOnlineBundle\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20200121112134 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE exact_import_log (id INT AUTO_INCREMENT NOT NULL, batch_id INT NOT NULL, message VARCHAR(255) NOT NULL, type INT NOT NULL, topic_node VARCHAR(255) NOT NULL, topic_code VARCHAR(255) NOT NULL, data_key VARCHAR(255) DEFAULT NULL, datetime DATETIME NOT NULL, INDEX import_batch_id_idx (batch_id), INDEX import_type_idx (type), INDEX import_topic_code_idx (topic_code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE exact_import_log');
    }
}
