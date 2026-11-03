<?php declare(strict_types=1);

namespace Tinect\Passkeys\Page\Account\Passkeys;

use Shopware\Storefront\Page\Page;

class AccountPasskeysPage extends Page
{
    /**
     * @var list<array{id: string, name: string, createdAt: string, lastUsedAt: ?string}>
     */
    protected array $passkeys = [];

    protected bool $passwordLoginDisabled = false;

    /**
     * @return list<array{id: string, name: string, createdAt: string, lastUsedAt: ?string}>
     */
    public function getPasskeys(): array
    {
        return $this->passkeys;
    }

    /**
     * @param list<array{id: string, name: string, createdAt: string, lastUsedAt: ?string}> $passkeys
     */
    public function setPasskeys(array $passkeys): void
    {
        $this->passkeys = $passkeys;
    }

    public function isPasswordLoginDisabled(): bool
    {
        return $this->passwordLoginDisabled;
    }

    public function setPasswordLoginDisabled(bool $passwordLoginDisabled): void
    {
        $this->passwordLoginDisabled = $passwordLoginDisabled;
    }
}
