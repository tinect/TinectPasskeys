<?php declare(strict_types=1);

namespace Tinect\Passkeys\Subscriber;

use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tinect\Passkeys\Event\PasskeyAddedEvent;

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
        $definition = $this->businessEventCollector->define(PasskeyAddedEvent::class);

        if ($definition !== null) {
            $event->getCollection()->set($definition->getName(), $definition);
        }
    }
}
