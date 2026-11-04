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
     * Same placeholder as in AccountService, keeps the response time equal to a real password check.
     * Generated per instance so it is never stored in source code and is safe under all PHP runtimes.
     */
    private readonly string $placeholderPasswordHash;

    public function __construct(
        #[AutowireDecorated]
        private readonly AbstractLoginRoute $decorated,
        private readonly AccountService $accountService,
        private readonly PasskeyRepository $passkeyRepository,
        private readonly RateLimiter $rateLimiter,
        private readonly RequestStack $requestStack,
    ) {
        $this->placeholderPasswordHash = password_hash(bin2hex(random_bytes(16)), \PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public function getDecorated(): AbstractLoginRoute
    {
        return $this->decorated;
    }

    public const SESSION_SUGGEST_PASSKEY = 'tinect_passkeys.suggest_passkey';

    public const SESSION_PASSWORD_VERIFIED = 'tinect_passkeys.password_verified';

    public function login(#[\SensitiveParameter] RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        // same normalization as LoginRoute, customers are stored with the punycode domain
        EmailIdnConverter::encodeDataBag($data);
        EmailIdnConverter::encodeDataBag($data, 'username');
        $email = $data->getString('email', $data->getString('username'));

        if ($email !== '' && $this->isPasswordLoginDisabled($email, $context)) {
            // the decorated route is not called, so its rate limiting has to be applied here
            $this->ensureNotThrottled($email);

            password_verify($data->getString('password'), $this->placeholderPasswordHash);

            // same error as wrong credentials, so it does not reveal that the account exists and uses passkeys
            throw CustomerException::badCredentials();
        }

        $response = $this->decorated->login($data, $context);

        // suggest adding a passkey after successful password login
        $session = $this->requestStack->getMainRequest()?->getSession();
        $session?->set(self::SESSION_SUGGEST_PASSKEY, true);
        $session?->set(self::SESSION_PASSWORD_VERIFIED, true);

        return $response;
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
