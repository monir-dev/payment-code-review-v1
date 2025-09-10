<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250910132448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer normalization: create customers table with customer_vault_id as primary key and remove customer fields from subscriptions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customers (
            customer_vault_id VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            first_name VARCHAR(255) NOT NULL,
            last_name VARCHAR(255) NOT NULL,
            street1 VARCHAR(255) NOT NULL,
            street2 VARCHAR(255) DEFAULT NULL,
            city VARCHAR(255) NOT NULL,
            state VARCHAR(255) NOT NULL,
            postal_code VARCHAR(20) NOT NULL,
            country VARCHAR(255) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(customer_vault_id)
        )');

        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_CUSTOMER_VAULT_ID FOREIGN KEY (customer_vault_id) REFERENCES customers (customer_vault_id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_CUSTOMER_VAULT_ID ON subscriptions (customer_vault_id)');
        $this->addSql('ALTER TABLE subscriptions DROP COLUMN customer_email');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions DROP CONSTRAINT FK_CUSTOMER_VAULT_ID');
        $this->addSql('DROP INDEX IDX_CUSTOMER_VAULT_ID');

        $this->addSql('ALTER TABLE subscriptions ADD customer_email VARCHAR(255) DEFAULT NULL');

        $this->addSql('DROP TABLE customers');
    }
}
