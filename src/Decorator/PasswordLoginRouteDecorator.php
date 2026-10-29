<?php declare(strict_types=1);

namespace Tinect\Passkeys\Decorator;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RequestStack;
use Tinect\Passkeys\Service\PasskeyRepository;

/**
 * Rejects the password login of customers, who disabled it in favour of their passkeys.
 * The passkey login uses AccountService::loginById() and is not affected.
 */
#[AsDecorator(decorates: LoginRoute::class)]
class PasswordLoginRouteDecorator extends AbstractLoginRoute
{
    /**
     * Same placeholder as in AccountService, keeps the response time equal to a real password check
     */
    private const PLACEHOLDER_PASSWORD_HASH = '$2y$12$PVcA5R6ri9kS.7FnFUBRIOLwqU//bCicx5RFxwecAAccbmZ7V7PKu';

    public function __construct(
        #[AutowireDecorated]
        private readonly AbstractLoginRoute $decorated,
        private readonly AccountService $accountService,
        private readonly PasskeyRepository $passkeyRepository,
        private readonly RateLimiter $rateLimiter,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getDecorated(): AbstractLoginRoute
    {
        return $this->decorated;
    }

    public function login(#[\SensitiveParameter] RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        // same normalization as LoginRoute, customers are stored with the punycode domain
        EmailIdnConverter::encodeDataBag($data);
        EmailIdnConverter::encodeDataBag($data, 'username');
        $email = $data->getString('email', $data->getString('username'));

        if ($email !== '' && $this->isPasswordLoginDisabled($email, $context)) {
            // the decorated route is not called, so its rate limiting has to be applied here
            $this->ensureNotThrottled($email);

            password_verify($data->getString('password'), self::PLACEHOLDER_PASSWORD_HASH);

            // same error as wrong credentials, so it does not reveal that the account exists and uses passkeys
            throw CustomerException::badCredentials();
        }

        return $this->decorated->login($data, $context);
    }

    /**
     * Same keys as LoginRoute, so blocked and regular attempts share the limit.
     * The counter is never reset here, as this login can not succeed.
     */
    private function ensureNotThrottled(string $email): void
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return;
        }

        $emailKey = strtolower($email);
        $clientIpKey = (string) $request->getClientIp();

        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::LOGIN_ROUTE, $emailKey . '-' . $clientIpKey);
            $this->rateLimiter->ensureAcceptedIfConfigured(RateLimiter::LOGIN_USER, $emailKey);
            $this->rateLimiter->ensureAcceptedIfConfigured(RateLimiter::LOGIN_CLIENT, $clientIpKey);
        } catch (RateLimitExceededException $exception) {
            throw CustomerException::customerAuthThrottledException($exception->getWaitTime(), $exception);
        }
    }

    private function isPasswordLoginDisabled(string $email, SalesChannelContext $context): bool
    {
        try {
            $customer = $this->accountService->getCustomerByEmail($email, $context);
        } catch (CustomerNotFoundException) {
            return false;
        }

        return $this->passkeyRepository->isPasswordLoginDisabled($customer->getId());
    }
}
