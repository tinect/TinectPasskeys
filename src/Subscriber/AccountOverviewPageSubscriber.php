<?php

declare(strict_types=1);

namespace Tinect\Passkeys\Subscriber;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tinect\Passkeys\Decorator\PasswordLoginRouteDecorator;

readonly class AccountOverviewPageSubscriber implements EventSubscriberInterface
{
    public const EXTENSION_NAME = 'tinectPasskeys';

    public static function getSubscribedEvents(): array
    {
        return [
            AccountOverviewPageLoadedEvent::class => 'onAccountOverviewPageLoaded',
        ];
    }

    public function onAccountOverviewPageLoaded(AccountOverviewPageLoadedEvent $event): void
    {
        $customer = $event->getSalesChannelContext()->getCustomer();
        if ($customer === null || $customer->getGuest()) {
            return;
        }

        $session = $event->getRequest()->getSession();
        $suggest = (bool) $session->get(PasswordLoginRouteDecorator::SESSION_SUGGEST_PASSKEY, false);
        $session->remove(PasswordLoginRouteDecorator::SESSION_SUGGEST_PASSKEY);

        $event->getPage()->addExtension(self::EXTENSION_NAME, new ArrayStruct([
            'showSetupModal' => $suggest,
        ]));
    }
}
