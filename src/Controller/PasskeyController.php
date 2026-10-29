<?php declare(strict_types=1);

namespace Tinect\Passkeys\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tinect\Passkeys\Event\AbstractPasswordLoginEvent;
use Tinect\Passkeys\Event\PasskeyAddedEvent;
use Tinect\Passkeys\Service\PasskeyRepository;
use Tinect\Passkeys\Service\PasswordLoginService;
use Tinect\Passkeys\Service\WebauthnService;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class PasskeyController extends StorefrontController
{
    private const MAX_NAME_LENGTH = 255;

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly WebauthnService $webauthnService,
        private readonly PasskeyRepository $passkeyRepository,
        private readonly PasswordLoginService $passwordLoginService,
        private readonly AccountService $accountService,
        #[Autowire(service: 'customer.repository')]
        private readonly EntityRepository $customerRepository,
        private readonly RateLimiter $rateLimiter,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @see https://w3c.github.io/webappsec-passkey-endpoints/passkey-endpoints.html
     */
    #[Route(
        path: '/.well-known/passkey-endpoints',
        name: 'frontend.tinect-passkeys.well-known',
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET]
    )]
    public function wellKnown(): JsonResponse
    {
        $profileUrl = $this->generateUrl(
            'frontend.account.profile.page',
            ['_fragment' => 'tinect-passkeys'],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new JsonResponse(['enroll' => $profileUrl, 'manage' => $profileUrl]);
    }

    #[Route(
        path: '/account/passkey/login/options',
        name: 'frontend.tinect-passkeys.login.options',
        defaults: ['XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function loginOptions(Request $request): Response
    {
        return $this->createJsonStringResponse($this->webauthnService->createRequestOptions($request));
    }

    #[Route(
        path: '/account/passkey/login',
        name: 'frontend.tinect-passkeys.login',
        defaults: ['XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function login(Request $request, SalesChannelContext $context): JsonResponse
    {
        $credentialJson = $request->request->getString('credential');
        $rateLimitKey = 'tinect-passkey-' . $request->getClientIp();

        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::LOGIN_ROUTE, $rateLimitKey);
        } catch (RateLimitExceededException) {
            return $this->createErrorResponse('tinect-passkeys.login.errorThrottled', Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $passkey = $this->passkeyRepository->findByCredentialId(
                $this->webauthnService->getAssertionCredentialId($credentialJson)
            ) ?? throw new \RuntimeException('Unknown passkey');

            $credentialRecord = $this->webauthnService->verifyAssertion(
                $request,
                $credentialJson,
                $passkey['credential'],
                $passkey['customerId']
            );

            $customer = $this->getCustomer($passkey['customerId'], $context);
            if ($customer === null || $customer->getGuest() || !$this->isCustomerConfirmed($customer)) {
                throw new \RuntimeException('Customer is not allowed to log in');
            }

            $this->accountService->loginById($customer->getId(), $context);
            $this->passkeyRepository->updateAfterLogin($passkey['id'], $this->webauthnService->serialize($credentialRecord));
        } catch (\Throwable $exception) {
            $this->logger->warning('Passkey login failed', [
                'reason' => $exception->getMessage(),
                'exceptionClass' => $exception::class,
                'clientIp' => $request->getClientIp(),
            ]);

            return $this->createErrorResponse('tinect-passkeys.login.error', Response::HTTP_UNAUTHORIZED);
        }

        $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $rateLimitKey);

        return new JsonResponse([
            'success' => true,
            'redirectUrl' => $this->getRedirectUrl($request),
        ]);
    }

    #[Route(
        path: '/account/passkey/register/options',
        name: 'frontend.tinect-passkeys.register.options',
        defaults: ['XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true, PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function registerOptions(Request $request, SalesChannelContext $context): Response
    {
        $customer = $this->getLoggedInCustomer($context);
        $rateLimitKey = strtolower($customer->getEmail()) . '-' . $request->getClientIp();

        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::LOGIN_ROUTE, $rateLimitKey);
            $this->accountService->getCustomerByLogin($customer->getEmail(), $request->request->getString('password'), $context);
        } catch (RateLimitExceededException) {
            return $this->createErrorResponse('tinect-passkeys.register.errorThrottled');
        } catch (BadCredentialsException|CustomerNotFoundException) {
            return $this->createErrorResponse('tinect-passkeys.register.errorPassword');
        }

        $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $rateLimitKey);

        $salesChannelName = $context->getSalesChannel()->getTranslation('name');

        return $this->createJsonStringResponse($this->webauthnService->createCreationOptions(
            $request,
            $customer->getId(),
            $customer->getEmail(),
            trim($customer->getFirstName() . ' ' . $customer->getLastName()),
            \is_string($salesChannelName) && $salesChannelName !== '' ? $salesChannelName : $request->getHost(),
            array_column($this->passkeyRepository->findByCustomerId($customer->getId()), 'credential')
        ));
    }

    #[Route(
        path: '/account/passkey/register',
        name: 'frontend.tinect-passkeys.register',
        defaults: ['XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true, PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function register(Request $request, SalesChannelContext $context): JsonResponse
    {
        $customer = $this->getLoggedInCustomer($context);

        $name = mb_substr(trim((string) preg_replace('/[\p{C}]+/u', ' ', $request->request->getString('name'))), 0, self::MAX_NAME_LENGTH);
        if ($name === '') {
            $name = $this->trans('tinect-passkeys.register.defaultName');
        }

        try {
            $credentialRecord = $this->webauthnService->verifyCreation($request, $request->request->getString('credential'), $customer->getId());

            $this->passkeyRepository->insert(
                $customer->getId(),
                $credentialRecord->publicKeyCredentialId,
                $this->webauthnService->serialize($credentialRecord),
                $name
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Passkey registration failed', [
                'customerId' => $customer->getId(),
                'reason' => $exception->getMessage(),
                'exceptionClass' => $exception::class,
            ]);

            return $this->createErrorResponse('tinect-passkeys.register.error');
        }

        // notifies the customer via flow (mail), so a passkey added by an attacker does not go unnoticed
        $this->eventDispatcher->dispatch(new PasskeyAddedEvent(
            $context,
            $customer,
            $name,
            $this->generateUrl('frontend.account.profile.page', ['_fragment' => 'tinect-passkeys'], UrlGeneratorInterface::ABSOLUTE_URL)
        ));

        $this->addFlash(self::SUCCESS, $this->trans('tinect-passkeys.register.success'));

        return new JsonResponse(['success' => true]);
    }

    #[Route(
        path: '/account/passkey/{passkeyId}/delete',
        name: 'frontend.tinect-passkeys.delete',
        requirements: ['passkeyId' => '[0-9a-f]{32}'],
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function delete(string $passkeyId, SalesChannelContext $context): Response
    {
        $customerId = $this->getLoggedInCustomer($context)->getId();
        $result = $this->passwordLoginService->removePasskey($passkeyId, $customerId, $context->getContext());

        if ($result['deleted']) {
            $this->logger->notice('Passkey removed by customer', ['customerId' => $customerId, 'passkeyId' => $passkeyId]);
            $this->addFlash(self::SUCCESS, $this->trans('tinect-passkeys.delete.success'));
        }

        if ($result['passwordLoginEnabled']) {
            $this->addFlash(self::INFO, $this->trans('tinect-passkeys.passwordLogin.reEnabledLastPasskey'));
        }

        return $this->redirectToRoute('frontend.account.profile.page', ['_fragment' => 'tinect-passkeys']);
    }

    #[Route(
        path: '/account/passkey/password-login',
        name: 'frontend.tinect-passkeys.password-login',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function passwordLogin(Request $request, SalesChannelContext $context): Response
    {
        $customerId = $this->getLoggedInCustomer($context)->getId();
        $disable = $request->request->getBoolean('disabled');

        if ($disable && !$this->passwordLoginService->disable($customerId, $context)) {
            $this->addFlash(self::DANGER, $this->trans('tinect-passkeys.passwordLogin.errorNoPasskey'));

            return $this->redirectToRoute('frontend.account.profile.page', ['_fragment' => 'tinect-passkeys']);
        }

        if (!$disable) {
            $this->passwordLoginService->enable($customerId, AbstractPasswordLoginEvent::REASON_CUSTOMER, $context->getContext());
        }

        $this->addFlash(self::SUCCESS, $this->trans($disable ? 'tinect-passkeys.passwordLogin.disabledSuccess' : 'tinect-passkeys.passwordLogin.enabledSuccess'));

        return $this->redirectToRoute('frontend.account.profile.page', ['_fragment' => 'tinect-passkeys']);
    }

    private function getLoggedInCustomer(SalesChannelContext $context): CustomerEntity
    {
        $customer = $context->getCustomer();

        if ($customer === null || $customer->getGuest()) {
            throw $this->createAccessDeniedException();
        }

        return $customer;
    }

    private function getCustomer(string $customerId, SalesChannelContext $context): ?CustomerEntity
    {
        if (!Uuid::isValid($customerId)) {
            return null;
        }

        return $this->customerRepository->search(new Criteria([$customerId]), $context->getContext())->getEntities()->first();
    }

    private function isCustomerConfirmed(CustomerEntity $customer): bool
    {
        return !$customer->getDoubleOptInRegistration() || $customer->getDoubleOptInConfirmDate();
    }

    private function getRedirectUrl(Request $request): string
    {
        $redirectTo = $request->request->getString('redirectTo');

        if (str_starts_with($redirectTo, 'frontend.') && $redirectTo !== 'frontend.account.login.page') {
            $parameters = json_decode($request->request->getString('redirectParameters', '[]'), true);

            try {
                return $this->generateUrl($redirectTo, \is_array($parameters) ? $parameters : []);
            } catch (\Throwable) {
                // fall through to the account overview
            }
        }

        return $this->generateUrl('frontend.account.home.page');
    }

    private function createJsonStringResponse(string $json): Response
    {
        return new Response($json, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    private function createErrorResponse(string $snippet, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->trans($snippet),
        ], $status);
    }
}
