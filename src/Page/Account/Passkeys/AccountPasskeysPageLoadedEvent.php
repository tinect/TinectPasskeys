<?php declare(strict_types=1);

namespace Tinect\Passkeys\Page\Account\Passkeys;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountPasskeysPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountPasskeysPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountPasskeysPage
    {
        return $this->page;
    }
}
