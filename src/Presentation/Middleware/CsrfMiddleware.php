<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Middleware;

use MailDesk\Infrastructure\Security\CsrfTokenManager;
use MailDesk\Presentation\Http\{Request, Response};

final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private CsrfTokenManager $csrf)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }
        $submitted = $request->header('X-CSRF-Token') ?? $request->post('_csrf');
        if (!$this->csrf->isValid(is_string($submitted) ? $submitted : null)) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Invalid or missing CSRF token. Reload the page and try again.'], 419);
            }
            return Response::html(
                '<!doctype html><meta charset="utf-8"><title>Page expired</title><p>Your form expired or the request was not valid. '
                . '<a href="/">Go back and try again</a>.</p>',
                419,
            );
        }
        return $next($request);
    }
}
