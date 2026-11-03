<?php declare(strict_types=1);

namespace Tinect\Passkeys\Migration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * The passkeys are managed on their own account page instead of the profile page.
 * Only the link created by the plugin is replaced, other changes of the merchant are kept.
 */
class Migration1793318900UpdatePasswordLoginMailLinks extends MigrationStep
{
    private const OLD_URL = '{{ rawUrl(\'frontend.account.profile.page\', [], salesChannel.domains|first.url) }}#tinect-passkeys';

    private const NEW_URL = '{{ rawUrl(\'frontend.tinect-passkeys.page\', [], salesChannel.domains|first.url) }}';

    #[\Override]
    public function getCreationTimestamp(): int
    {
        return 1793318900;
    }

    #[\Override]
    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `mail_template_translation`
             SET `content_html` = REPLACE(`content_html`, :oldUrl, :newUrl),
                 `content_plain` = REPLACE(`content_plain`, :oldUrl, :newUrl),
                 `updated_at` = :now
             WHERE `mail_template_id` IN (:templateIds)
               AND (`content_html` LIKE :search OR `content_plain` LIKE :search)',
            [
                'oldUrl' => self::OLD_URL,
                'newUrl' => self::NEW_URL,
                'search' => '%' . addcslashes(self::OLD_URL, '%_\\') . '%',
                'now' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'templateIds' => Uuid::fromHexToBytesList([
                    Migration1793318700CreatePasswordLoginMailFlows::DISABLED_MAIL_TEMPLATE_ID,
                    Migration1793318700CreatePasswordLoginMailFlows::ENABLED_MAIL_TEMPLATE_ID,
                ]),
            ],
            ['templateIds' => ArrayParameterType::BINARY]
        );
    }
}
