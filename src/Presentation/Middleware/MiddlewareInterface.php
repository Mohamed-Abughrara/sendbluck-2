<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Middleware;

use MailDesk\Presentation\Http\{Request, Response};

interface MiddlewareInterface
{
    /** @param callable(Request):Response $next */
    public function handle(Request $request, callable $next): Response;
}
