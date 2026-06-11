<?php

declare(strict_types=1);

namespace App\Support;

use Waaseyaa\User\Middleware\CsrfMiddleware;

/**
 * A lazily-evaluated CSRF token for Twig: registered as the `csrf_token`
 * global before page render, stringified only when a template actually prints
 * it (so the session is live by then; SessionMiddleware runs before
 * dispatch). In bare-Twig tests with no session the value degrades to an
 * empty string instead of erroring, which is also what `default('')` covers
 * where the global was never registered at all.
 */
final class CsrfTokenValue implements \Stringable
{
    public function __toString(): string
    {
        try {
            return CsrfMiddleware::token();
        } catch (\Throwable) {
            return '';
        }
    }
}
