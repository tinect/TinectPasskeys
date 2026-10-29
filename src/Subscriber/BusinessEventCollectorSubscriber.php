<?php declare(strict_types=1);

namespace Tinect\Passkeys\Subscriber;

use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tinect\Passkeys\Event\PasskeyAddedEvent;
use Tinect\Passkeys\Event\PasswordLoginDisabledEvent;
use Tinect\Passkeys\Event\PasswordLoginEnabledEvent;

readonly class BusinessEventCollectorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private BusinessEventCollector $businessEventCollector,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BusinessEventCollectorEvent::NAME => 'onCollect',
        ];
    }

    public function onCollect(BusinessEventCollectorEvent $event): void
    {
        foreach ([PasskeyAddedEvent::class, PasswordLoginDisabledEvent::class, PasswordLoginEnabledEvent::class] as $class) {
            $definition = $this->businessEventCollector->define($class);

            if ($definition !== null) {
                $event->getCollection()->set($definition->getName(), $definition);
            }
        }
    }
}
