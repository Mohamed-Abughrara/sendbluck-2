<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Security;

use MailDesk\Infrastructure\Session\NativeSession;

final class CsrfTokenManager
{
    private const KEY = '_csrf_token';

    public function __construct(private NativeSession $session)
    {
    }

    public function token(): string
    {
        $t = $this->session->get(self::KEY);
        if (!is_string($t) || $t === '') {
            $t = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $t);
        }
        return $t;
    }

    public function isValid(?string $submitted): bool
    {
        $t = $this->session->get(self::KEY);
        return is_string($t) && $t !== '' && is_string($submitted) && hash_equals($t, $submitted);
    }
}
