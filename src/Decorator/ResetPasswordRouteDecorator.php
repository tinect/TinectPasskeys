<?php declare(strict_types=1);

namespace Tinect\Passkeys\Decorator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractResetPasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Tinect\Passkeys\Event\AbstractPasswordLoginEvent;
use Tinect\Passkeys\Service\PasswordLoginService;

/**
 * A successful password recovery proves access to the e-mail address, so the password login is enabled again.
 * Otherwise a customer who lost all devices with passkeys would be locked out.
 */
#[AsDecorator(decorates: ResetPasswordRoute::class)]
class ResetPasswordRouteDecorator extends AbstractResetPasswordRoute
{
    public function __construct(
        #[AutowireDecorated]
        private readonly AbstractResetPasswordRoute $decorated,
        private readonly Connection $connection,
        private readonly PasswordLoginService $passwordLoginService,
    ) {
    }

    public function getDecorated(): AbstractResetPasswordRoute
    {
        return $this->decorated;
    }

    public function resetPassword(RequestDataBag $data, SalesChannelContext $context): SuccessResponse
    {
        // the recovery entry is deleted by the decorated route, so the customer has to be resolved before
        $customerId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`customer_id`)) FROM `customer_recovery` WHERE `hash` = :hash',
            ['hash' => $data->getString('hash')]
        );

        $response = $this->decorated->resetPassword($data, $context);

        if (\is_string($customerId) && Uuid::isValid($customerId)) {
            $this->passwordLoginService->enable($customerId, AbstractPasswordLoginEvent::REASON_RECOVERY, $context->getContext());
        }

        return $response;
    }
}
