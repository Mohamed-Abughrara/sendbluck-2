<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Middleware;

use MailDesk\Application\Port\RateLimiterInterface;
use MailDesk\Presentation\Http\{Request, Response, View};

final class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var array<string, list<array{string,int,int}>> bucket => [[label, max, windowSeconds], ...] */
    private array $buckets;

    public function __construct(
        private RateLimiterInterface $limiter,
        private View $view,
        private string $bucket,
        int $sendPerMin,
        int $sendPerDay,
    ) {
        $this->buckets = [
            'send' => [['min', $sendPerMin, 60], ['day', $sendPerDay, 86400]],
            'test' => [['min', 5, 60]],
            'search' => [['min', 60, 60]],
        ];
    }

    public function handle(Request $request, callable $next): Response
    {
        $who = $request->session->userId() !== null ? 'user:' . $request->session->userId() : 'ip:' . $request->ip();
        $wait = 0;
        foreach ($this->buckets[$this->bucket] ?? [] as [$label, $max, $window]) {
            $wait = max($wait, $this->limiter->attempt("rate:{$this->bucket}:{$label}:{$who}", $max, $window));
        }
        if ($wait === 0) {
            return $next($request);
        }

        $what = $this->bucket === 'send' ? 'messages' : 'requests';
        $msg = "Too many {$what}. Try again in {$wait} seconds.";
        if ($request->wantsJson()) {
            $r = Response::json(['error' => $msg], 429);
        } else {
            $r = Response::html($this->view->render('errors/error', ['code' => 429, 'message' => $msg], 'Too many requests', ''), 429);
        }
        return $r->withHeader('Retry-After', (string) $wait);
    }
}
