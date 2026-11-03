<?php declare(strict_types=1);

namespace Tinect\Passkeys\Page\Account\Passkeys;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tinect\Passkeys\Service\PasskeyRepository;

readonly class AccountPasskeysPageLoader
{
    public function __construct(
        #[Autowire(service: GenericPageLoader::class)]
        private GenericPageLoaderInterface $genericLoader,
        private PasskeyRepository $passkeyRepository,
        private EventDispatcherInterface $eventDispatcher,
        private TranslatorInterface $translator,
    ) {
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext, CustomerEntity $customer): AccountPasskeysPage
    {
        $page = AccountPasskeysPage::createFrom($this->genericLoader->load($request, $salesChannelContext));

        $metaInformation = $page->getMetaInformation() ?? new MetaInformation();
        $metaInformation->setRobots('noindex,follow');
        $metaInformation->setMetaTitle($this->translator->trans('tinect-passkeys.profile.title'));
        $page->setMetaInformation($metaInformation);

        // the serialized credential is not needed in the storefront and must not end up in the page
        $page->setPasskeys(array_map(
            static fn (array $passkey): array => [
                'id' => $passkey['id'],
                'name' => $passkey['name'],
                'createdAt' => $passkey['createdAt'],
                'lastUsedAt' => $passkey['lastUsedAt'],
            ],
            $this->passkeyRepository->findByCustomerId($customer->getId())
        ));
        $page->setPasswordLoginDisabled($this->passkeyRepository->isPasswordLoginDisabled($customer->getId()));

        $this->eventDispatcher->dispatch(new AccountPasskeysPageLoadedEvent($page, $salesChannelContext, $request));

        return $page;
    }
}
