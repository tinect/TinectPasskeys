<?php declare(strict_types=1);

namespace Tinect\Passkeys\Decorator;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeEmailRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangeEmailRoute;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Tinect\Passkeys\Service\PasskeyRepository;

/**
 * Rejects the e-mail change of customers, who disabled the password login.
 * Otherwise a hijacked session and the password were enough to take over the account: change the e-mail address,
 * reset the password via "Forgot password", which enables the password login again.
 * The customer enables the password login first, which has to be confirmed with a passkey.
 */
#[AsDecorator(decorates: ChangeEmailRoute::class)]
class ChangeEmailRouteDecorator extends AbstractChangeEmailRoute
{
    /**
     * Storefront shows the snippet "error.<code>" at the e-mail field
     */
    final public const VIOLATION_CODE = 'TINECT_PASSKEYS_PASSWORD_LOGIN_DISABLED';

    public function __construct(
        #[AutowireDecorated]
        private readonly AbstractChangeEmailRoute $decorated,
        private readonly PasskeyRepository $passkeyRepository,
    ) {
    }

    public function getDecorated(): AbstractChangeEmailRoute
    {
        return $this->decorated;
    }

    public function change(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        if ($this->passkeyRepository->isPasswordLoginDisabled($customer->getId())) {
            throw new ConstraintViolationException(
                new ConstraintViolationList([
                    new ConstraintViolation(
                        'The e-mail address can not be changed while the password login is disabled.',
                        null,
                        [],
                        '',
                        '/email',
                        $requestDataBag->get('email'),
                        null,
                        self::VIOLATION_CODE
                    ),
                ]),
                // the input is used to refill the form, the password must not be sent back
                array_diff_key($requestDataBag->all(), ['password' => true])
            );
        }

        return $this->decorated->change($requestDataBag, $context, $customer);
    }
}
