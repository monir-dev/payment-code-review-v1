<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250910005132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move amount, frequency, and currency_code from subscriptions to plans table (data normalization)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plans ADD currency_code VARCHAR(50) DEFAULT \'USD\' NOT NULL');

        $this->addSql('ALTER TABLE subscriptions DROP COLUMN amount');
        $this->addSql('ALTER TABLE subscriptions DROP COLUMN frequency');
        $this->addSql('ALTER TABLE subscriptions DROP COLUMN currency_code');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions ADD amount DOUBLE PRECISION NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE subscriptions ADD frequency VARCHAR(50) NOT NULL DEFAULT \'monthly\'');
        $this->addSql('ALTER TABLE subscriptions ADD currency_code VARCHAR(50) DEFAULT \'USD\'');

        $this->addSql('ALTER TABLE plans DROP COLUMN currency_code');
    }
}
