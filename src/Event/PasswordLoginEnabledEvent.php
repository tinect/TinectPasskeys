<?php declare(strict_types=1);

namespace Tinect\Passkeys\Event;

class PasswordLoginEnabledEvent extends AbstractPasswordLoginEvent
{
    final public const EVENT_NAME = 'tinect_passkeys.password_login.enabled';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
