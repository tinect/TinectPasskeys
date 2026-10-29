<?php declare(strict_types=1);

namespace Tinect\Passkeys\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tinect\Passkeys\Service\PasskeyRepository;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['api']])]
class PasskeyAdminController extends AbstractController
{
    public function __construct(
        private readonly PasskeyRepository $passkeyRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/api/_action/tinect-passkeys/customer/{customerId}',
        name: 'api.action.tinect-passkeys.customer.list',
        requirements: ['customerId' => '[0-9a-f]{32}'],
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:read']],
        methods: [Request::METHOD_GET]
    )]
    public function list(string $customerId): JsonResponse
    {
        if (!Uuid::isValid($customerId)) {
            return new JsonResponse(['passkeys' => [], 'passwordLoginDisabled' => false]);
        }

        return new JsonResponse([
            'passkeys' => array_map(
                static fn (array $passkey): array => [
                    'id' => $passkey['id'],
                    'name' => $passkey['name'],
                    'createdAt' => $passkey['createdAt'],
                    'lastUsedAt' => $passkey['lastUsedAt'],
                ],
                $this->passkeyRepository->findByCustomerId($customerId)
            ),
            'passwordLoginDisabled' => $this->passkeyRepository->isPasswordLoginDisabled($customerId),
        ]);
    }

    #[Route(
        path: '/api/_action/tinect-passkeys/customer/{customerId}/{passkeyId}',
        name: 'api.action.tinect-passkeys.customer.delete',
        requirements: ['customerId' => '[0-9a-f]{32}', 'passkeyId' => '[0-9a-f]{32}'],
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:update']],
        methods: [Request::METHOD_DELETE]
    )]
    public function delete(string $customerId, string $passkeyId, Context $context): Response
    {
        if ($this->passkeyRepository->delete($passkeyId, $customerId) > 0) {
            $source = $context->getSource();

            $this->logger->notice('Passkey removed by administration user', [
                'customerId' => $customerId,
                'passkeyId' => $passkeyId,
                'userId' => $source instanceof AdminApiSource ? $source->getUserId() : null,
            ]);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Support can enable the password login again, e.g. when the customer lost the devices with their passkeys.
     */
    #[Route(
        path: '/api/_action/tinect-passkeys/customer/{customerId}/enable-password-login',
        name: 'api.action.tinect-passkeys.customer.enable-password-login',
        requirements: ['customerId' => '[0-9a-f]{32}'],
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:update']],
        methods: [Request::METHOD_POST]
    )]
    public function enablePasswordLogin(string $customerId, Context $context): Response
    {
        $this->passkeyRepository->setPasswordLoginDisabled($customerId, false);

        $source = $context->getSource();
        $this->logger->notice('Password login enabled by administration user', [
            'customerId' => $customerId,
            'userId' => $source instanceof AdminApiSource ? $source->getUserId() : null,
        ]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
