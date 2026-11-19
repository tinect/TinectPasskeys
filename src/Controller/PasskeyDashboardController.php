<?php declare(strict_types=1);

namespace Tinect\Passkeys\Controller;

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Tinect\Passkeys\Service\PasskeyStatistics;

/**
 * Data for the passkey widgets of the FroshAdminDashboard plugin.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['api']])]
class PasskeyDashboardController extends AbstractController
{
    private const MAX_RECENT_LIMIT = 50;

    private const MAX_HISTORY_DAYS = 366;

    public function __construct(
        private readonly PasskeyStatistics $passkeyStatistics,
    ) {
    }

    #[Route(
        path: '/api/_action/tinect-passkeys/dashboard/summary',
        name: 'api.action.tinect-passkeys.dashboard.summary',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:read']],
        methods: [Request::METHOD_GET]
    )]
    public function summary(Request $request): JsonResponse
    {
        return new JsonResponse(
            $this->passkeyStatistics->getSummary($this->salesChannelId($request))
            + ['activeDays' => PasskeyStatistics::ACTIVE_DAYS]
        );
    }

    #[Route(
        path: '/api/_action/tinect-passkeys/dashboard/recent',
        name: 'api.action.tinect-passkeys.dashboard.recent',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:read']],
        methods: [Request::METHOD_GET]
    )]
    public function recent(Request $request): JsonResponse
    {
        $limit = min(self::MAX_RECENT_LIMIT, max(1, $request->query->getInt('limit', 10)));

        return new JsonResponse([
            'passkeys' => $this->passkeyStatistics->getRecentPasskeys($limit, $this->salesChannelId($request)),
        ]);
    }

    #[Route(
        path: '/api/_action/tinect-passkeys/dashboard/added-per-day',
        name: 'api.action.tinect-passkeys.dashboard.added-per-day',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:read']],
        methods: [Request::METHOD_GET]
    )]
    public function addedPerDay(Request $request): JsonResponse
    {
        $days = min(self::MAX_HISTORY_DAYS, max(1, $request->query->getInt('days', 30)));

        return new JsonResponse([
            'days' => $this->passkeyStatistics->getAddedPerDay(
                $days,
                $this->timeZone($request),
                $this->salesChannelId($request)
            ),
        ]);
    }

    private function salesChannelId(Request $request): ?string
    {
        $salesChannelId = $request->query->getString('salesChannelId');

        return Uuid::isValid($salesChannelId) ? strtolower($salesChannelId) : null;
    }

    private function timeZone(Request $request): \DateTimeZone
    {
        $timeZone = $request->query->getString('timeZone');

        if (\in_array($timeZone, \DateTimeZone::listIdentifiers(), true)) {
            return new \DateTimeZone($timeZone);
        }

        return new \DateTimeZone('UTC');
    }
}
