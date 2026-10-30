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
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\LogAware;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class PasskeyAddedEvent extends Event implements SalesChannelAware, ShopwareSalesChannelEvent, CustomerAware, MailAware, ScalarValuesAware, FlowEventAware, LogAware
{
    final public const EVENT_NAME = 'tinect_passkeys.passkey.added';

    final public const PASSKEY_NAME = 'passkeyName';

    final public const PROFILE_URL = 'profileUrl';

    final public const CREATED_AT = 'createdAt';

    /**
     * Shortened client IP, see PasskeyController::register()
     */
    final public const IP_ADDRESS = 'ipAddress';

    final public const DEVICE = 'device';

    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly CustomerEntity $customer,
        private readonly string $passkeyName,
        private readonly string $profileUrl,
        private readonly string $createdAt,
        private readonly string $ipAddress,
        private readonly string $device,
    ) {
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            self::PASSKEY_NAME => $this->passkeyName,
            self::PROFILE_URL => $this->profileUrl,
            self::CREATED_AT => $this->createdAt,
            self::IP_ADDRESS => $this->ipAddress,
            self::DEVICE => $this->device,
        ];
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getCustomerId(): string
    {
        return $this->customer->getId();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannelId();
    }

    public function getPasskeyName(): string
    {
        return $this->passkeyName;
    }

    public function getProfileUrl(): string
    {
        return $this->profileUrl;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getDevice(): string
    {
        return $this->device;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(CustomerAware::CUSTOMER, new EntityType(CustomerDefinition::class))
            ->add(self::PASSKEY_NAME, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(self::PROFILE_URL, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(self::CREATED_AT, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(self::IP_ADDRESS, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(self::DEVICE, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct([
            $this->customer->getEmail() => trim($this->customer->getFirstName() . ' ' . $this->customer->getLastName()),
        ]);
    }

    /**
     * Without the IP address and device, so they are only sent to the customer and not kept in the event log
     */
    public function getLogData(): array
    {
        return [
            'customerId' => $this->customer->getId(),
            'customerNumber' => $this->customer->getCustomerNumber(),
            'passkeyName' => $this->passkeyName,
        ];
    }

    public function getLogLevel(): Level
    {
        return Level::Notice;
    }
}
