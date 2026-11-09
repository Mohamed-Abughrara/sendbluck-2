<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Http;

use FastRoute\{Dispatcher, RouteCollector};
use MailDesk\Application\Port\RateLimiterInterface;
use MailDesk\Domain\Exception\{NotFound, RateLimited};
use MailDesk\Infrastructure\Container\Container;
use MailDesk\Infrastructure\Logging\FileErrorLogger;
use MailDesk\Infrastructure\Security\CsrfTokenManager;
use MailDesk\Infrastructure\Session\NativeSession;
use MailDesk\Presentation\Middleware\{AuthMiddleware, CsrfMiddleware, RateLimitMiddleware, SecurityHeadersMiddleware};

final class Kernel
{
    /** @param array<string,mixed> $settings */
    public function __construct(private Container $c, private array $settings)
    {
    }

    public function run(): void
    {
        /** @var NativeSession $session */
        $session = $this->c->get(NativeSession::class);
        $session->start();
        $request = Request::fromGlobals($session, $this->settings['trusted_proxies']);
        ViewContext::setCsrf($this->c->get(CsrfTokenManager::class)->token());

        $response = $this->handle($request);
        $response->send($session);
    }

    public function handle(Request $request): Response
    {
        $headers = new SecurityHeadersMiddleware($this->settings['is_production']);
        return $headers->handle($request, fn (Request $r): Response => $this->safely($r));
    }

    private function safely(Request $request): Response
    {
        try {
            if ($this->settings['is_production'] && !$request->isHttps()) {
                $base = (string) $this->settings['app_url'];
                if (str_starts_with($base, 'https://')) {
                    return Response::redirect(rtrim($base, '/') . $request->path, 308);
                }
                return Response::html('HTTPS is required.', 400);
            }
            return $this->dispatch($request);
        } catch (NotFound $e) {
            return $this->error($request, 404, $e->getMessage());
        } catch (RateLimited $e) {
            return $this->error($request, 429, $e->getMessage())->withHeader('Retry-After', (string) $e->retryAfter);
        } catch (\Throwable $e) {
            /** @var FileErrorLogger $log */
            $log = $this->c->get(FileErrorLogger::class);
            $log->error('Unhandled exception: ' . $e::class . ': ' . $e->getMessage(), [
                'path' => $request->path,
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            $msg = 'Something went wrong. Reference: ' . $log->requestId();
            if (!$this->settings['is_production']) {
                $msg .= ' — ' . $e::class . ': ' . $e->getMessage();
            }
            return $this->error($request, 500, $msg);
        }
    }

    private function dispatch(Request $request): Response
    {
        $routes = require $this->settings['root'] . '/config/routes.php';
        $dispatcher = \FastRoute\simpleDispatcher(static function (RouteCollector $r) use ($routes): void {
            foreach ($routes as [$method, $path, $handler, $options]) {
                $r->addRoute($method, $path, [$handler, $options]);
            }
        });

        $info = $dispatcher->dispatch($request->method, $request->path);
        if ($info[0] === Dispatcher::NOT_FOUND) {
            return $this->error($request, 404, 'Page not found.');
        }
        if ($info[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            return $this->error($request, 405, 'Method not allowed.')->withHeader('Allow', implode(', ', $info[1]));
        }

        [[$handler, $options], $vars] = [$info[1], $info[2]];
        $request->params = array_map('strval', $vars);

        $stack = [];
        if (($options['auth'] ?? true) === true) {
            $stack[] = new AuthMiddleware();
        }
        if (($options['csrf'] ?? false) === true) {
            $stack[] = new CsrfMiddleware($this->c->get(CsrfTokenManager::class));
        }
        if (isset($options['rate'])) {
            $stack[] = new RateLimitMiddleware(
                $this->c->get(RateLimiterInterface::class),
                $this->c->get(View::class),
                (string) $options['rate'],
                $this->settings['rate_send_per_min'],
                $this->settings['rate_send_per_day'],
            );
        }

        [$class, $action] = $handler;
        $core = function (Request $r) use ($class, $action): Response {
            $controller = $this->c->get($class);
            return $controller->$action($r);
        };
        $pipeline = array_reduce(
            array_reverse($stack),
            static fn (callable $next, $mw): callable => static fn (Request $r): Response => $mw->handle($r, $next),
            $core,
        );

        return $pipeline($request);
    }

    private function error(Request $request, int $code, string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => $message], $code);
        }
        /** @var View $view */
        $view = $this->c->get(View::class);
        $titles = [404 => 'Not found', 405 => 'Method not allowed', 429 => 'Too many requests', 500 => 'Server error'];
        return Response::html($view->render('errors/error', ['code' => $code, 'message' => $message], $titles[$code] ?? 'Error', ''), $code);
    }
}
