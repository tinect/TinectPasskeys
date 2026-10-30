<?php declare(strict_types=1);

namespace Tinect\Passkeys\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Adds the time, the shortened IP address and the device to the "passkey added" mail.
 * The passkey name is chosen by whoever added the passkey, so it alone is no reliable hint for the customer.
 * Templates customized by the merchant are kept.
 */
class Migration1793318800UpdatePasskeyAddedMailTemplate extends MigrationStep
{
    private const TRANSLATIONS = [
        'en' => [
            'html' => <<<'TWIG'
<div style="font-family:arial; font-size:12px;">
    <p>{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},</p>
    <p>a new passkey was just added to your customer account at {{ salesChannel.translated.name }}:</p>
    <p>
        {% if createdAt|default %}Time: {{ createdAt|format_datetime('medium', 'short', locale='en-GB') }}<br>{% endif %}
        {% if device|default %}Device: {{ device }}<br>{% endif %}
        {% if ipAddress|default %}IP address (shortened): {{ ipAddress }}<br>{% endif %}
        Name chosen when adding: "{{ passkeyName|e }}"
    </p>
    <p>If this was you, there is nothing else to do.</p>
    <p>If this was not you, please remove the passkey in your account right away and change your password:<br>
        <a href="{{ profileUrl }}">{{ profileUrl }}</a></p>
</div>
TWIG,
            'plain' => <<<'TWIG'
{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},

a new passkey was just added to your customer account at {{ salesChannel.translated.name }}:

{% if createdAt|default %}Time: {{ createdAt|format_datetime('medium', 'short', locale='en-GB') }}
{% endif %}{% if device|default %}Device: {{ device }}
{% endif %}{% if ipAddress|default %}IP address (shortened): {{ ipAddress }}
{% endif %}Name chosen when adding: "{{ passkeyName }}"

If this was you, there is nothing else to do.

If this was not you, please remove the passkey in your account right away and change your password:
{{ profileUrl }}
TWIG,
        ],
        'de' => [
            'html' => <<<'TWIG'
<div style="font-family:arial; font-size:12px;">
    <p>{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},</p>
    <p>zu Ihrem Kundenkonto bei {{ salesChannel.translated.name }} wurde soeben ein neuer Passkey hinzugefügt:</p>
    <p>
        {% if createdAt|default %}Zeitpunkt: {{ createdAt|format_datetime('medium', 'short', locale='de-DE') }}<br>{% endif %}
        {% if device|default %}Gerät: {{ device }}<br>{% endif %}
        {% if ipAddress|default %}IP-Adresse (gekürzt): {{ ipAddress }}<br>{% endif %}
        Beim Hinzufügen gewählte Bezeichnung: „{{ passkeyName|e }}“
    </p>
    <p>Wenn Sie das waren, ist nichts weiter zu tun.</p>
    <p>Wenn nicht, entfernen Sie den Passkey bitte umgehend in Ihrem Kundenkonto und ändern Sie Ihr Passwort:<br>
        <a href="{{ profileUrl }}">{{ profileUrl }}</a></p>
</div>
TWIG,
            'plain' => <<<'TWIG'
{{ customer.salutation.letterName }} {{ customer.firstName }} {{ customer.lastName }},

zu Ihrem Kundenkonto bei {{ salesChannel.translated.name }} wurde soeben ein neuer Passkey hinzugefügt:

{% if createdAt|default %}Zeitpunkt: {{ createdAt|format_datetime('medium', 'short', locale='de-DE') }}
{% endif %}{% if device|default %}Gerät: {{ device }}
{% endif %}{% if ipAddress|default %}IP-Adresse (gekürzt): {{ ipAddress }}
{% endif %}Beim Hinzufügen gewählte Bezeichnung: „{{ passkeyName }}“

Wenn Sie das waren, ist nichts weiter zu tun.

Wenn nicht, entfernen Sie den Passkey bitte umgehend in Ihrem Kundenkonto und ändern Sie Ihr Passwort:
{{ profileUrl }}
TWIG,
        ],
    ];

    #[\Override]
    public function getCreationTimestamp(): int
    {
        return 1793318800;
    }

    #[\Override]
    public function update(Connection $connection): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach (self::TRANSLATIONS as $locale => $translation) {
            $original = Migration1793318500CreatePasskeyAddedMailFlow::TRANSLATIONS[$locale];

            foreach (['html' => 'content_html', 'plain' => 'content_plain'] as $key => $column) {
                $connection->executeStatement(
                    \sprintf(
                        'UPDATE `mail_template_translation` SET `%1$s` = :content, `updated_at` = :now
                         WHERE `mail_template_id` = :templateId AND `%1$s` = :original',
                        $column
                    ),
                    [
                        'content' => $translation[$key],
                        'original' => $original[$key],
                        'templateId' => Uuid::fromHexToBytes(Migration1793318500CreatePasskeyAddedMailFlow::MAIL_TEMPLATE_ID),
                        'now' => $now,
                    ]
                );
            }
        }
    }
}
