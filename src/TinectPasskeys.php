<?php declare(strict_types=1);

namespace Tinect\Passkeys;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Tinect\Passkeys\Migration\Migration1793318500CreatePasskeyAddedMailFlow;

class TinectPasskeys extends Plugin
{
    public function executeComposerCommands(): bool
    {
        return true;
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }

        $connection = $this->container?->get(Connection::class);
        if (!$connection instanceof Connection) {
            return;
        }

        $connection->executeStatement('DROP TABLE IF EXISTS `tinect_passkey`');
        $connection->executeStatement('DROP TABLE IF EXISTS `tinect_passkey_customer_setting`');
        $connection->executeStatement(
            'DELETE FROM `flow` WHERE `id` = :flowId',
            ['flowId' => Uuid::fromHexToBytes(Migration1793318500CreatePasskeyAddedMailFlow::FLOW_ID)]
        );
        $connection->executeStatement(
            'DELETE FROM `mail_template` WHERE `mail_template_type_id` = :typeId',
            ['typeId' => Uuid::fromHexToBytes(Migration1793318500CreatePasskeyAddedMailFlow::MAIL_TEMPLATE_TYPE_ID)]
        );
        $connection->executeStatement(
            'DELETE FROM `mail_template_type` WHERE `id` = :typeId',
            ['typeId' => Uuid::fromHexToBytes(Migration1793318500CreatePasskeyAddedMailFlow::MAIL_TEMPLATE_TYPE_ID)]
        );
    }
}
