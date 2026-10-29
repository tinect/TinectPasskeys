<?php declare(strict_types=1);

namespace Tinect\Passkeys\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Tinect\Passkeys\Event\PasswordLoginDisabledEvent;
use Tinect\Passkeys\Event\PasswordLoginEnabledEvent;

class Migration1793318700CreatePasswordLoginMailFlows extends MigrationStep
{
    final public const DISABLED_MAIL_TEMPLATE_TYPE = 'tinect_passkeys.password_login_disabled';

    final public const DISABLED_MAIL_TEMPLATE_TYPE_ID = '019a3a0c5e2e7c4d9f1b2a3c4d5e6f80';

    final public const DISABLED_MAIL_TEMPLATE_ID = '019a3a0c5e2e7c4d9f1b2a3c4d5e6f81';

    final public const DISABLED_FLOW_ID = '019a3a0c5e2e7c4d9f1b2a3c4d5e6f82';

    final public const ENABLED_MAIL_TEMPLATE_TYPE = 'tinect_passkeys.password_login_enabled';

    final public const ENABLED_MAIL_TEMPLATE_TYPE_ID = '019a3a0c5e2e7c4d9f1b2a3c4d5e6f90';

    final public const ENABLED_MAIL_TEMPLATE_ID = '019a3a0c5e2e7c4d9f1b2a3c4d5e6f91';

    final public const ENABLED_FLOW_ID = '019a3a0c5e2e7c4d9f1b2a3c4d5e6f92';

    private const PROFILE_URL = '{{ rawUrl(\'frontend.account.profile.page\', [], salesChannel.domains|first.url) }}#tinect-passkeys';

    #[\Override]
    public function getCreationTimestamp(): int
    {
        return 1793318700;
    }

    #[\Override]
    public function update(Connection $connection): void
    {
        $this->createMailFlow(
            $connection,
            self::DISABLED_MAIL_TEMPLATE_TYPE,
            self::DISABLED_MAIL_TEMPLATE_TYPE_ID,
            self::DISABLED_MAIL_TEMPLATE_ID,
            self::DISABLED_FLOW_ID,
            PasswordLoginDisabledEvent::EVENT_NAME,
            'Password login disabled',
            $this->getDisabledTranslations()
        );

        $this->createMailFlow(
            $connection,
            self::ENABLED_MAIL_TEMPLATE_TYPE,
            self::ENABLED_MAIL_TEMPLATE_TYPE_ID,
            self::ENABLED_MAIL_TEMPLATE_ID,
            self::ENABLED_FLOW_ID,
            PasswordLoginEnabledEvent::EVENT_NAME,
            'Password login enabled',
            $this->getEnabledTranslations()
        );
    }

    /**
     * @return array<'de'|'en', array{typeName: string, subject: string, description: string, html: string, plain: string}>
     */
    private function getDisabledTranslations(): array
    {
        $url = self::PROFILE_URL;

        return [
            'en' => [
                'typeName' => 'Password login disabled',
                'subject' => 'Password login disabled for your account',
                'description' => 'Security notice to the customer when the password login was disabled',
                'html' => <<<TWIG
<div style="font-family:arial; font-size:12px;">
    <p>{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},</p>
    <p>the password login was just disabled for your customer account at {{ salesChannel.translated.name }}. From now on, you log in with your passkey only.</p>
    <p>If this was not you, please use "Forgot password" on the login page right away. This enables the password login again. Then check your passkeys in your account:<br>
        <a href="{$url}">{$url}</a></p>
</div>
TWIG,
                'plain' => <<<TWIG
{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},

the password login was just disabled for your customer account at {{ salesChannel.translated.name }}. From now on, you log in with your passkey only.

If this was not you, please use "Forgot password" on the login page right away. This enables the password login again. Then check your passkeys in your account:
{$url}
TWIG,
            ],
            'de' => [
                'typeName' => 'Anmeldung per Passwort deaktiviert',
                'subject' => 'Anmeldung per Passwort für Ihr Kundenkonto deaktiviert',
                'description' => 'Sicherheitshinweis an den Kunden, wenn die Anmeldung per Passwort deaktiviert wurde',
                'html' => <<<TWIG
<div style="font-family:arial; font-size:12px;">
    <p>{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},</p>
    <p>für Ihr Kundenkonto bei {{ salesChannel.translated.name }} wurde soeben die Anmeldung per Passwort deaktiviert. Sie melden sich ab sofort nur noch mit Ihrem Passkey an.</p>
    <p>Wenn Sie das nicht waren, nutzen Sie bitte umgehend „Passwort vergessen“ auf der Anmeldeseite. Damit wird die Anmeldung per Passwort wieder aktiviert. Prüfen Sie anschließend Ihre Passkeys im Kundenkonto:<br>
        <a href="{$url}">{$url}</a></p>
</div>
TWIG,
                'plain' => <<<TWIG
{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},

für Ihr Kundenkonto bei {{ salesChannel.translated.name }} wurde soeben die Anmeldung per Passwort deaktiviert. Sie melden sich ab sofort nur noch mit Ihrem Passkey an.

Wenn Sie das nicht waren, nutzen Sie bitte umgehend „Passwort vergessen“ auf der Anmeldeseite. Damit wird die Anmeldung per Passwort wieder aktiviert. Prüfen Sie anschließend Ihre Passkeys im Kundenkonto:
{$url}
TWIG,
            ],
        ];
    }

    /**
     * @return array<'de'|'en', array{typeName: string, subject: string, description: string, html: string, plain: string}>
     */
    private function getEnabledTranslations(): array
    {
        $url = self::PROFILE_URL;

        $enReason = <<<'TWIG'
{% if reason == 'recovery' %}because your password was reset via "Forgot password"{% elseif reason == 'lastPasskeyRemoved' %}because the last passkey was removed from your account{% elseif reason == 'administration' %}by our customer service{% else %}in your account settings{% endif %}
TWIG;
        $deReason = <<<'TWIG'
{% if reason == 'recovery' %}weil Ihr Passwort über „Passwort vergessen“ neu gesetzt wurde{% elseif reason == 'lastPasskeyRemoved' %}weil der letzte Passkey aus Ihrem Kundenkonto entfernt wurde{% elseif reason == 'administration' %}durch unseren Kundenservice{% else %}in Ihren Kontoeinstellungen{% endif %}
TWIG;

        return [
            'en' => [
                'typeName' => 'Password login enabled',
                'subject' => 'Password login enabled again for your account',
                'description' => 'Security notice to the customer when the password login was enabled again',
                'html' => <<<TWIG
<div style="font-family:arial; font-size:12px;">
    <p>{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},</p>
    <p>the password login for your customer account at {{ salesChannel.translated.name }} was just enabled again {$enReason}.</p>
    <p>If this was not you, please change your password right away and check your passkeys in your account:<br>
        <a href="{$url}">{$url}</a></p>
</div>
TWIG,
                'plain' => <<<TWIG
{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},

the password login for your customer account at {{ salesChannel.translated.name }} was just enabled again {$enReason}.

If this was not you, please change your password right away and check your passkeys in your account:
{$url}
TWIG,
            ],
            'de' => [
                'typeName' => 'Anmeldung per Passwort wieder aktiviert',
                'subject' => 'Anmeldung per Passwort für Ihr Kundenkonto wieder aktiviert',
                'description' => 'Sicherheitshinweis an den Kunden, wenn die Anmeldung per Passwort wieder aktiviert wurde',
                'html' => <<<TWIG
<div style="font-family:arial; font-size:12px;">
    <p>{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},</p>
    <p>für Ihr Kundenkonto bei {{ salesChannel.translated.name }} wurde soeben die Anmeldung per Passwort wieder aktiviert, {$deReason}.</p>
    <p>Wenn Sie das nicht waren, ändern Sie bitte umgehend Ihr Passwort und prüfen Sie Ihre Passkeys im Kundenkonto:<br>
        <a href="{$url}">{$url}</a></p>
</div>
TWIG,
                'plain' => <<<TWIG
{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},

für Ihr Kundenkonto bei {{ salesChannel.translated.name }} wurde soeben die Anmeldung per Passwort wieder aktiviert, {$deReason}.

Wenn Sie das nicht waren, ändern Sie bitte umgehend Ihr Passwort und prüfen Sie Ihre Passkeys im Kundenkonto:
{$url}
TWIG,
            ],
        ];
    }

    /**
     * @param array<'de'|'en', array{typeName: string, subject: string, description: string, html: string, plain: string}> $translations
     */
    private function createMailFlow(
        Connection $connection,
        string $technicalName,
        string $typeIdHex,
        string $templateIdHex,
        string $flowIdHex,
        string $eventName,
        string $flowName,
        array $translations,
    ): void {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $typeId = Uuid::fromHexToBytes($typeIdHex);
        $templateId = Uuid::fromHexToBytes($templateIdHex);
        $flowId = Uuid::fromHexToBytes($flowIdHex);

        if (!$this->exists($connection, 'mail_template_type', $typeId)) {
            $connection->insert('mail_template_type', [
                'id' => $typeId,
                'technical_name' => $technicalName,
                'available_entities' => json_encode(['customer' => 'customer', 'salesChannel' => 'sales_channel'], \JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);
        }

        if (!$this->exists($connection, 'mail_template', $templateId)) {
            $connection->insert('mail_template', [
                'id' => $templateId,
                'mail_template_type_id' => $typeId,
                'system_default' => 1,
                'created_at' => $now,
            ]);
        }

        foreach ($this->getLanguageIds($connection) as $languageId => $locale) {
            $translation = $translations[$locale];

            $connection->executeStatement(
                'INSERT IGNORE INTO `mail_template_type_translation` (`mail_template_type_id`, `language_id`, `name`, `created_at`)
                 VALUES (:typeId, :languageId, :name, :now)',
                ['typeId' => $typeId, 'languageId' => $languageId, 'name' => $translation['typeName'], 'now' => $now]
            );

            $connection->executeStatement(
                'INSERT IGNORE INTO `mail_template_translation`
                    (`mail_template_id`, `language_id`, `sender_name`, `subject`, `description`, `content_html`, `content_plain`, `created_at`)
                 VALUES (:templateId, :languageId, :senderName, :subject, :description, :html, :plain, :now)',
                [
                    'templateId' => $templateId,
                    'languageId' => $languageId,
                    'senderName' => '{{ salesChannel.translated.name }}',
                    'subject' => $translation['subject'],
                    'description' => $translation['description'],
                    'html' => $translation['html'],
                    'plain' => $translation['plain'],
                    'now' => $now,
                ]
            );
        }

        if ($this->exists($connection, 'flow', $flowId)) {
            return;
        }

        $connection->insert('flow', [
            'id' => $flowId,
            'name' => $flowName,
            'event_name' => $eventName,
            'priority' => 1,
            'invalid' => 0,
            'active' => 1,
            'created_at' => $now,
        ]);

        $connection->insert('flow_sequence', [
            'id' => Uuid::randomBytes(),
            'flow_id' => $flowId,
            'action_name' => SendMailAction::ACTION_NAME,
            'config' => json_encode([
                'replyTo' => null,
                'mailTemplateId' => $templateIdHex,
                'documentTypeIds' => [],
                'recipient' => ['data' => [], 'type' => 'default'],
            ], \JSON_THROW_ON_ERROR),
            'position' => 1,
            'display_group' => 1,
            'created_at' => $now,
        ]);
    }

    /**
     * German languages get the German texts, all others (including the system default language) the English ones.
     *
     * @return array<string, 'de'|'en'>
     */
    private function getLanguageIds(Connection $connection): array
    {
        /** @var array<string, string> $languages */
        $languages = $connection->fetchAllKeyValue(
            'SELECT `language`.`id`, `locale`.`code`
             FROM `language`
             INNER JOIN `locale` ON `locale`.`id` = `language`.`translation_code_id`'
        );

        $result = [];
        foreach ($languages as $languageId => $code) {
            $result[$languageId] = str_starts_with($code, 'de-') ? 'de' : 'en';
        }

        $result[Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)] ??= 'en';

        return $result;
    }

    private function exists(Connection $connection, string $table, string $id): bool
    {
        return (bool) $connection->fetchOne(
            \sprintf('SELECT 1 FROM `%s` WHERE `id` = :id', $table),
            ['id' => $id]
        );
    }
}
