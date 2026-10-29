<?php declare(strict_types=1);

namespace Tinect\Passkeys\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1793318400CreatePasskeyTable extends MigrationStep
{
    #[\Override]
    public function getCreationTimestamp(): int
    {
        return 1793318400;
    }

    #[\Override]
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `tinect_passkey` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `credential_id` VARBINARY(1023) NOT NULL,
                `credential` JSON NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `last_used_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.tinect_passkey.credential_id` (`credential_id`),
                KEY `idx.tinect_passkey.customer_id` (`customer_id`),
                CONSTRAINT `json.tinect_passkey.credential` CHECK (JSON_VALID(`credential`)),
                CONSTRAINT `fk.tinect_passkey.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }
}
