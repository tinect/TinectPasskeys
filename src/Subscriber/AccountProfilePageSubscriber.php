<?php declare(strict_types=1);

namespace Tinect\Passkeys\Subscriber;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tinect\Passkeys\Service\PasskeyRepository;

readonly class AccountProfilePageSubscriber implements EventSubscriberInterface
{
    public const EXTENSION_NAME = 'tinectPasskeys';

    public function __construct(
        private PasskeyRepository $passkeyRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AccountProfilePageLoadedEvent::class => 'onAccountProfilePageLoaded',
        ];
    }

    public function onAccountProfilePageLoaded(AccountProfilePageLoadedEvent $event): void
    {
        $customer = $event->getSalesChannelContext()->getCustomer();
        if ($customer === null || $customer->getGuest()) {
            return;
        }

        $passkeys = array_map(
            static fn (array $passkey): array => [
                'id' => $passkey['id'],
                'name' => $passkey['name'],
                'createdAt' => $passkey['createdAt'],
                'lastUsedAt' => $passkey['lastUsedAt'],
            ],
            $this->passkeyRepository->findByCustomerId($customer->getId())
        );

        $event->getPage()->addExtension(self::EXTENSION_NAME, new ArrayStruct([
            'passkeys' => $passkeys,
            'passwordLoginDisabled' => $this->passkeyRepository->isPasswordLoginDisabled($customer->getId()),
        ]));
    }
}
