<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Middleware;

use MailDesk\Presentation\Http\{Request, Response};

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if ($request->session->validate()) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return Response::json(['error' => $request->session->expired ? 'Your session expired. Please sign in again.' : 'Unauthenticated.'], 401);
        }
        return Response::redirect($request->session->expired ? '/login?expired=1' : '/login');
    }
}
