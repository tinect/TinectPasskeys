<?php declare(strict_types=1);

namespace Tinect\Passkeys\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-type PasskeyRow array{id: string, credential: string, name: string, lastUsedAt: ?string, createdAt: string}
 */
readonly class PasskeyRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return list<PasskeyRow>
     */
    public function findByCustomerId(string $customerId): array
    {
        /** @var list<PasskeyRow> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, `credential`, `name`, `last_used_at` AS `lastUsedAt`, `created_at` AS `createdAt`
             FROM `tinect_passkey`
             WHERE `customer_id` = :customerId
             ORDER BY `created_at` ASC',
            ['customerId' => Uuid::fromHexToBytes($customerId)]
        );

        return $rows;
    }

    /**
     * @return array{id: string, customerId: string, credential: string}|null
     */
    public function findByCredentialId(string $credentialId): ?array
    {
        /** @var array{id: string, customerId: string, credential: string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, LOWER(HEX(`customer_id`)) AS `customerId`, `credential`
             FROM `tinect_passkey`
             WHERE `credential_id` = :credentialId',
            ['credentialId' => $credentialId]
        );

        return $row ?: null;
    }

    public function insert(string $customerId, string $credentialId, string $credential, string $name): void
    {
        $this->connection->insert('tinect_passkey', [
            'id' => Uuid::randomBytes(),
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'credential_id' => $credentialId,
            'credential' => $credential,
            'name' => $name,
            'created_at' => $this->now(),
        ]);
    }

    public function updateAfterLogin(string $id, string $credential): void
    {
        $now = $this->now();

        $this->connection->update(
            'tinect_passkey',
            ['credential' => $credential, 'last_used_at' => $now, 'updated_at' => $now],
            ['id' => Uuid::fromHexToBytes($id)]
        );
    }

    /**
     * Use PasswordLoginService::removePasskey(), which keeps the password login setting consistent.
     *
     * @return int number of deleted passkeys
     */
    public function delete(string $id, string $customerId): int
    {
        return (int) $this->connection->delete('tinect_passkey', [
            'id' => Uuid::fromHexToBytes($id),
            'customer_id' => Uuid::fromHexToBytes($customerId),
        ]);
    }

    public function countByCustomerId(string $customerId): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `tinect_passkey` WHERE `customer_id` = :customerId',
            ['customerId' => Uuid::fromHexToBytes($customerId)]
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    public function isPasswordLoginDisabled(string $customerId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT `password_login_disabled` FROM `tinect_passkey_customer_setting` WHERE `customer_id` = :customerId',
            ['customerId' => Uuid::fromHexToBytes($customerId)]
        );
    }

    /**
     * Use PasswordLoginService, which also dispatches the flow events.
     *
     * @return bool true when the setting has changed
     */
    public function setPasswordLoginDisabled(string $customerId, bool $disabled): bool
    {
        if ($this->isPasswordLoginDisabled($customerId) === $disabled) {
            return false;
        }

        $now = $this->now();

        $this->connection->executeStatement(
            'INSERT INTO `tinect_passkey_customer_setting` (`customer_id`, `password_login_disabled`, `created_at`)
             VALUES (:customerId, :disabled, :now)
             ON DUPLICATE KEY UPDATE `password_login_disabled` = :disabled, `updated_at` = :now',
            ['customerId' => Uuid::fromHexToBytes($customerId), 'disabled' => (int) $disabled, 'now' => $now]
        );

        return true;
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
