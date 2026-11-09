<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Middleware;

use MailDesk\Presentation\Http\{Request, Response};

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(private bool $production)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
                . "frame-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'",
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];
        if ($this->production) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($headers as $k => $v) {
            $response->withHeader($k, $v);
        }
        return $response;
    }
}
