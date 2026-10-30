<?php declare(strict_types=1);

namespace Tinect\Passkeys\Service;

/**
 * Describes the browser and operating system of a user agent roughly, e.g. "Chrome / Windows".
 * Only used for the security notices, so customers can tell whether it was their device.
 */
class DeviceDescriber
{
    /**
     * Checked in order, as most user agents contain several of the tokens
     */
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'EdgiOS/' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser/' => 'Samsung Internet',
        'Firefox/' => 'Firefox',
        'FxiOS/' => 'Firefox',
        'CriOS/' => 'Chrome',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    private const OPERATING_SYSTEMS = [
        'iPhone' => 'iOS',
        'iPad' => 'iPadOS',
        'Android' => 'Android',
        'Windows' => 'Windows',
        'Mac OS X' => 'macOS',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    public function describe(?string $userAgent): string
    {
        if ($userAgent === null || $userAgent === '') {
            return '';
        }

        return implode(' / ', array_filter([
            $this->find($userAgent, self::BROWSERS),
            $this->find($userAgent, self::OPERATING_SYSTEMS),
        ]));
    }

    /**
     * @param array<string, string> $tokens
     */
    private function find(string $userAgent, array $tokens): ?string
    {
        foreach ($tokens as $token => $name) {
            if (str_contains($userAgent, $token)) {
                return $name;
            }
        }

        return null;
    }
}
