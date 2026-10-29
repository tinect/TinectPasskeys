<?php declare(strict_types=1);

namespace Tinect\Passkeys\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Tinect\Passkeys\Event\PasskeyAddedEvent;

class Migration1793318500CreatePasskeyAddedMailFlow extends MigrationStep
{
    final public const MAIL_TEMPLATE_TYPE = 'tinect_passkeys.passkey_added';

    final public const MAIL_TEMPLATE_TYPE_ID = '019a3a0c5e2e7c4d9f1b2a3c4d5e6f70';

    final public const MAIL_TEMPLATE_ID = '019a3a0c5e2e7c4d9f1b2a3c4d5e6f71';

    final public const FLOW_ID = '019a3a0c5e2e7c4d9f1b2a3c4d5e6f72';

    private const TRANSLATIONS = [
        'en' => [
            'typeName' => 'Passkey added',
            'subject' => 'A new passkey was added to your account',
            'description' => 'Security notice to the customer when a passkey was added',
            'html' => <<<'TWIG'
<div style="font-family:arial; font-size:12px;">
    <p>{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},</p>
    <p>a new passkey "{{ passkeyName|e }}" was just added to your customer account at {{ salesChannel.translated.name }}.</p>
    <p>If this was you, there is nothing else to do.</p>
    <p>If this was not you, please remove the passkey in your account right away and change your password:<br>
        <a href="{{ profileUrl }}">{{ profileUrl }}</a></p>
</div>
TWIG,
            'plain' => <<<'TWIG'
{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},

a new passkey "{{ passkeyName }}" was just added to your customer account at {{ salesChannel.translated.name }}.

If this was you, there is nothing else to do.

If this was not you, please remove the passkey in your account right away and change your password:
{{ profileUrl }}
TWIG,
        ],
        'de' => [
            'typeName' => 'Passkey hinzugefügt',
            'subject' => 'Neuer Passkey zu Ihrem Kundenkonto hinzugefügt',
            'description' => 'Sicherheitshinweis an den Kunden, wenn ein Passkey hinzugefügt wurde',
            'html' => <<<'TWIG'
<div style="font-family:arial; font-size:12px;">
    <p>{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},</p>
    <p>zu Ihrem Kundenkonto bei {{ salesChannel.translated.name }} wurde soeben ein neuer Passkey „{{ passkeyName|e }}“ hinzugefügt.</p>
    <p>Wenn Sie das waren, ist nichts weiter zu tun.</p>
    <p>Wenn nicht, entfernen Sie den Passkey bitte umgehend in Ihrem Kundenkonto und ändern Sie Ihr Passwort:<br>
        <a href="{{ profileUrl }}">{{ profileUrl }}</a></p>
</div>
TWIG,
            'plain' => <<<'TWIG'
{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},

zu Ihrem Kundenkonto bei {{ salesChannel.translated.name }} wurde soeben ein neuer Passkey „{{ passkeyName }}“ hinzugefügt.

Wenn Sie das waren, ist nichts weiter zu tun.

Wenn nicht, entfernen Sie den Passkey bitte umgehend in Ihrem Kundenkonto und ändern Sie Ihr Passwort:
{{ profileUrl }}
TWIG,
        ],
    ];

    #[\Override]
    public function getCreationTimestamp(): int
    {
        return 1793318500;
    }

    #[\Override]
    public function update(Connection $connection): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $typeId = Uuid::fromHexToBytes(self::MAIL_TEMPLATE_TYPE_ID);
        $templateId = Uuid::fromHexToBytes(self::MAIL_TEMPLATE_ID);

        if (!$this->exists($connection, 'mail_template_type', $typeId)) {
            $connection->insert('mail_template_type', [
                'id' => $typeId,
                'technical_name' => self::MAIL_TEMPLATE_TYPE,
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
            $translation = self::TRANSLATIONS[$locale];

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

        $flowId = Uuid::fromHexToBytes(self::FLOW_ID);
        if ($this->exists($connection, 'flow', $flowId)) {
            return;
        }

        $connection->insert('flow', [
            'id' => $flowId,
            'name' => 'Passkey added',
            'event_name' => PasskeyAddedEvent::EVENT_NAME,
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
                'mailTemplateId' => self::MAIL_TEMPLATE_ID,
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
