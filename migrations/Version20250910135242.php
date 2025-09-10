<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250910135242 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove original_transaction_id column from subscriptions table as transaction linking is now handled via subscription_id in transactions table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions DROP COLUMN original_transaction_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions ADD original_transaction_id VARCHAR(255) DEFAULT NULL');
    }
}
