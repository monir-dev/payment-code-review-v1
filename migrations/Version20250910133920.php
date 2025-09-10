<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250910133920 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscription_id column to payment_transactions table for better transaction tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_transactions_tbl ADD subscription_id VARCHAR(255) DEFAULT NULL');

        $this->addSql('CREATE INDEX IDX_TRANSACTIONS_SUBSCRIPTION_ID ON payment_transactions_tbl (subscription_id)');

        $this->addSql('UPDATE payment_transactions_tbl pt
                       SET subscription_id = s.subscription_id
                       FROM subscriptions s
                       WHERE pt.transaction_id = s.original_transaction_id
                       AND s.original_transaction_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS IDX_TRANSACTIONS_SUBSCRIPTION_ID');

        $this->addSql('ALTER TABLE payment_transactions_tbl DROP COLUMN subscription_id');
    }
}
