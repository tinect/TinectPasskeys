<?php declare(strict_types=1);

namespace Tinect\Passkeys\Event;

use Monolog\Level;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Log\LogAware;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Works without a sales channel context, as the password login can also be changed by the administration.
 */
abstract class AbstractPasswordLoginEvent extends Event implements SalesChannelAware, CustomerAware, MailAware, ScalarValuesAware, FlowEventAware, LogAware
{
    final public const REASON = 'reason';

    final public const REASON_CUSTOMER = 'customer';

    final public const REASON_RECOVERY = 'recovery';

    final public const REASON_LAST_PASSKEY_REMOVED = 'lastPasskeyRemoved';

    final public const REASON_ADMINISTRATION = 'administration';

    public function __construct(
        private readonly Context $context,
        private readonly CustomerEntity $customer,
        private readonly string $reason,
    ) {
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [self::REASON => $this->reason];
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getCustomerId(): string
    {
        return $this->customer->getId();
    }

    public function getSalesChannelId(): string
    {
        return $this->customer->getSalesChannelId();
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(CustomerAware::CUSTOMER, new EntityType(CustomerDefinition::class))
            ->add(self::REASON, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct([
            $this->customer->getEmail() => trim($this->customer->getFirstName() . ' ' . $this->customer->getLastName()),
        ]);
    }

    public function getLogData(): array
    {
        return [
            'customerId' => $this->customer->getId(),
            'customerNumber' => $this->customer->getCustomerNumber(),
            'reason' => $this->reason,
        ];
    }

    public function getLogLevel(): Level
    {
        return Level::Notice;
    }
}
