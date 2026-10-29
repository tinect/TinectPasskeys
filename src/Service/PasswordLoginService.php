<?php declare(strict_types=1);

namespace Tinect\Passkeys\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tinect\Passkeys\Event\AbstractPasswordLoginEvent;
use Tinect\Passkeys\Event\PasswordLoginDisabledEvent;
use Tinect\Passkeys\Event\PasswordLoginEnabledEvent;

/**
 * Single place to change the password login setting, so every change notifies the customer via flow.
 */
readonly class PasswordLoginService
{
    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private PasskeyRepository $passkeyRepository,
        #[Autowire(service: 'customer.repository')]
        private EntityRepository $customerRepository,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool false when the customer has no passkey, the password login stays enabled then
     */
    public function disable(string $customerId, SalesChannelContext $context): bool
    {
        if ($this->passkeyRepository->countByCustomerId($customerId) === 0) {
            return false;
        }

        if ($this->passkeyRepository->setPasswordLoginDisabled($customerId, true)) {
            $this->dispatch(PasswordLoginDisabledEvent::class, $customerId, AbstractPasswordLoginEvent::REASON_CUSTOMER, $context->getContext(), false);
        }

        return true;
    }

    /**
     * @param AbstractPasswordLoginEvent::REASON_* $reason
     * @param bool $useCustomerLanguage true outside of the storefront, e.g. in the administration
     *
     * @return bool true when the password login was disabled before
     */
    public function enable(string $customerId, string $reason, Context $context, bool $useCustomerLanguage = false): bool
    {
        if (!$this->passkeyRepository->setPasswordLoginDisabled($customerId, false)) {
            return false;
        }

        $this->dispatch(PasswordLoginEnabledEvent::class, $customerId, $reason, $context, $useCustomerLanguage);

        return true;
    }

    /**
     * Removes the passkey. When it was the last one, the password login is enabled again,
     * so the customer can not lock themselves out.
     *
     * @return array{deleted: bool, passwordLoginEnabled: bool}
     */
    public function removePasskey(string $passkeyId, string $customerId, Context $context, bool $useCustomerLanguage = false): array
    {
        if ($this->passkeyRepository->delete($passkeyId, $customerId) === 0) {
            return ['deleted' => false, 'passwordLoginEnabled' => false];
        }

        $passwordLoginEnabled = $this->passkeyRepository->countByCustomerId($customerId) === 0
            && $this->enable($customerId, AbstractPasswordLoginEvent::REASON_LAST_PASSKEY_REMOVED, $context, $useCustomerLanguage);

        return ['deleted' => true, 'passwordLoginEnabled' => $passwordLoginEnabled];
    }

    /**
     * @param class-string<AbstractPasswordLoginEvent> $eventClass
     */
    private function dispatch(string $eventClass, string $customerId, string $reason, Context $context, bool $useCustomerLanguage): void
    {
        $customer = $this->customerRepository->search(
            (new Criteria([$customerId]))->addAssociation('salutation'),
            $context
        )->getEntities()->first();

        if (!$customer instanceof CustomerEntity) {
            return;
        }

        if ($useCustomerLanguage) {
            $context = $this->createCustomerLanguageContext($customer, $context);
        }

        $this->logger->notice('Passkeys: password login changed', [
            'event' => $eventClass,
            'customerId' => $customerId,
            'reason' => $reason,
        ]);

        $this->eventDispatcher->dispatch(new $eventClass($context, $customer, $reason));
    }

    /**
     * Mails are rendered in the language of the event context, which is the admin language outside of the storefront.
     */
    private function createCustomerLanguageContext(CustomerEntity $customer, Context $context): Context
    {
        return new Context(
            $context->getSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            array_values(array_unique([$customer->getLanguageId(), Defaults::LANGUAGE_SYSTEM]))
        );
    }
}
