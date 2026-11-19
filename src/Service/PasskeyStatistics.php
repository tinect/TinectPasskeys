<?php declare(strict_types=1);

namespace Tinect\Passkeys\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Read-only aggregations for the administration dashboard widgets.
 *
 * @phpstan-type RecentPasskeyRow array{id: string, name: string, createdAt: string, lastUsedAt: ?string, customerId: string, firstName: string, lastName: string, customerNumber: string, email: string}
 */
readonly class PasskeyStatistics
{
    public const ACTIVE_DAYS = 30;

    /**
     * Customers who can log in: no guests, no inactive customers, no unconfirmed double opt-in registrations.
     */
    private const LOGIN_CAPABLE_CUSTOMER_CONDITION = ' AND `customer`.`guest` = 0
        AND `customer`.`active` = 1
        AND NOT (`customer`.`double_opt_in_registration` = 1 AND `customer`.`double_opt_in_confirm_date` IS NULL)';

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Only customers who can log in count, see LOGIN_CAPABLE_CUSTOMER_CONDITION.
     *
     * @return array{customers: int, customersWithPasskey: int, activeCustomers: int, passkeys: int, passwordLoginDisabled: int}
     */
    public function getSummary(?string $salesChannelId): array
    {
        [$salesChannelCondition, $params] = $this->salesChannelCondition($salesChannelId);

        $passkeys = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS `passkeys`,
                    COUNT(DISTINCT `passkey`.`customer_id`) AS `customersWithPasskey`,
                    COUNT(DISTINCT CASE WHEN `passkey`.`last_used_at` >= :activeSince THEN `passkey`.`customer_id` END) AS `activeCustomers`
             FROM `tinect_passkey` `passkey`
             INNER JOIN `customer` ON `customer`.`id` = `passkey`.`customer_id`
             WHERE 1 = 1' . self::LOGIN_CAPABLE_CUSTOMER_CONDITION . $salesChannelCondition,
            $params + [
                'activeSince' => (new \DateTimeImmutable(\sprintf('-%d days', self::ACTIVE_DAYS)))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        ) ?: [];

        $passwordLoginDisabled = $this->connection->fetchOne(
            'SELECT COUNT(*)
             FROM `tinect_passkey_customer_setting` `setting`
             INNER JOIN `customer` ON `customer`.`id` = `setting`.`customer_id`
             WHERE `setting`.`password_login_disabled` = 1' . self::LOGIN_CAPABLE_CUSTOMER_CONDITION . $salesChannelCondition,
            $params
        );

        $customers = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `customer` WHERE 1 = 1' . self::LOGIN_CAPABLE_CUSTOMER_CONDITION . $salesChannelCondition,
            $params
        );

        return [
            'customers' => $this->toInt($customers),
            'customersWithPasskey' => $this->toInt($passkeys['customersWithPasskey'] ?? 0),
            'activeCustomers' => $this->toInt($passkeys['activeCustomers'] ?? 0),
            'passkeys' => $this->toInt($passkeys['passkeys'] ?? 0),
            'passwordLoginDisabled' => $this->toInt($passwordLoginDisabled),
        ];
    }

    /**
     * @return list<RecentPasskeyRow>
     */
    public function getRecentPasskeys(int $limit, ?string $salesChannelId): array
    {
        [$salesChannelCondition, $params] = $this->salesChannelCondition($salesChannelId);

        /** @var list<RecentPasskeyRow> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`passkey`.`id`)) AS `id`,
                    `passkey`.`name`,
                    `passkey`.`created_at` AS `createdAt`,
                    `passkey`.`last_used_at` AS `lastUsedAt`,
                    LOWER(HEX(`customer`.`id`)) AS `customerId`,
                    `customer`.`first_name` AS `firstName`,
                    `customer`.`last_name` AS `lastName`,
                    `customer`.`customer_number` AS `customerNumber`,
                    `customer`.`email`
             FROM `tinect_passkey` `passkey`
             INNER JOIN `customer` ON `customer`.`id` = `passkey`.`customer_id`
             WHERE 1 = 1' . $salesChannelCondition . '
             ORDER BY `passkey`.`created_at` DESC
             LIMIT ' . max(1, $limit),
            $params
        );

        return $rows;
    }

    /**
     * Number of added passkeys per day in the given time zone, oldest day first. Days without passkeys are omitted.
     *
     * @return list<array{date: string, count: int}>
     */
    public function getAddedPerDay(int $days, \DateTimeZone $timeZone, ?string $salesChannelId): array
    {
        [$salesChannelCondition, $params] = $this->salesChannelCondition($salesChannelId);

        // Database values are UTC. Grouping by hour and shifting each hour into the time zone in PHP
        // keeps the day boundaries correct without depending on the MySQL time zone tables.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DATE_FORMAT(`passkey`.`created_at`, \'%Y-%m-%d %H:00:00\') AS `hour`, COUNT(*) AS `count`
             FROM `tinect_passkey` `passkey`
             INNER JOIN `customer` ON `customer`.`id` = `passkey`.`customer_id`
             WHERE `passkey`.`created_at` >= :since' . $salesChannelCondition . '
             GROUP BY `hour`',
            $params + [
                'since' => (new \DateTimeImmutable(\sprintf('-%d days', $days)))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $utc = new \DateTimeZone('UTC');
        $perDay = [];

        foreach ($rows as $row) {
            if (!\is_string($row['hour'])) {
                continue;
            }

            $date = (new \DateTimeImmutable($row['hour'], $utc))->setTimezone($timeZone)->format('Y-m-d');
            $perDay[$date] = ($perDay[$date] ?? 0) + $this->toInt($row['count']);
        }

        ksort($perDay);

        $result = [];
        foreach ($perDay as $date => $count) {
            $result[] = ['date' => (string) $date, 'count' => $count];
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function salesChannelCondition(?string $salesChannelId): array
    {
        if ($salesChannelId === null) {
            return ['', []];
        }

        return [
            ' AND `customer`.`sales_channel_id` = :salesChannelId',
            ['salesChannelId' => Uuid::fromHexToBytes($salesChannelId)],
        ];
    }

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
