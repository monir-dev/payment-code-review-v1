<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250907171107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create plans and subscriptions tables with relationship and constraints';
    }

    public function up(Schema $schema): void
    {
        // 1. Create Plans Table
        $this->addSql('CREATE TABLE plans (
            id SERIAL NOT NULL,
            plan_id VARCHAR(255) NOT NULL,
            plan_name VARCHAR(255) NOT NULL,
            amount DOUBLE PRECISION NOT NULL,
            frequency VARCHAR(50) NOT NULL,
            day_frequency INT NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_356798D1E899029B ON plans (plan_id)');

        // 2. Create Subscriptions Table
        $this->addSql('CREATE TABLE subscriptions (
            id SERIAL NOT NULL,
            subscription_id VARCHAR(255) NOT NULL,
            customer_vault_id VARCHAR(255) NOT NULL,
            amount DOUBLE PRECISION NOT NULL,
            currency_code VARCHAR(50) DEFAULT \'USD\',
            frequency VARCHAR(50) NOT NULL,
            start_date DATE NOT NULL,
            next_charge_date DATE DEFAULT NULL,
            status VARCHAR(20) DEFAULT \'active\',
            customer_email VARCHAR(255) DEFAULT NULL,
            last4_digits VARCHAR(4) DEFAULT NULL,
            plan_id INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_4778A019283A749 ON subscriptions (subscription_id)');

        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A01E899029B FOREIGN KEY (plan_id) REFERENCES plans (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE INDEX IDX_4778A01E899029B ON subscriptions (plan_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions DROP CONSTRAINT FK_4778A01E899029B');
        $this->addSql('DROP INDEX IDX_4778A01E899029B');
        $this->addSql('DROP INDEX UNIQ_4778A019283A749');

        $this->addSql('DROP TABLE subscriptions');

        $this->addSql('DROP INDEX UNIQ_356798D1E899029B');
        $this->addSql('DROP TABLE plans');
    }
}
