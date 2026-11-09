<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Controller;

use MailDesk\Application\Auth\Authenticate;
use MailDesk\Application\Port\AuditLoggerInterface;
use MailDesk\Domain\Exception\{InvalidCredentials, RateLimited};
use MailDesk\Infrastructure\Session\NativeSession;
use MailDesk\Presentation\Http\{Request, Response, View};

final class AuthController
{
    public function __construct(
        private Authenticate $authenticate,
        private NativeSession $session,
        private AuditLoggerInterface $audit,
        private View $view,
    ) {
    }

    public function showLogin(Request $request): Response
    {
        if ($this->session->validate()) {
            return Response::redirect('/dashboard');
        }
        $notice = $request->query('expired') !== null ? 'Your session expired. Please sign in again.' : null;
        return Response::html($this->view->render('auth/login', ['notice' => $notice], 'Sign in'));
    }

    public function login(Request $request): Response
    {
        $email = mb_substr($request->str('email'), 0, 254);
        $password = mb_substr($request->str('password'), 0, 128);

        $errors = [];
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }
        if ($errors !== []) {
            return Response::html($this->view->render('auth/login', ['notice' => null], 'Sign in', '', 'layout', ['email' => $email], $errors), 422);
        }

        try {
            $user = $this->authenticate->execute($email, $password, $request->ip());
        } catch (RateLimited $e) {
            $mins = (int) ceil($e->retryAfter / 60);
            $msg = $mins >= 2 ? "Too many attempts. Try again in {$mins} minutes." : "Too many attempts. Try again in {$e->retryAfter} seconds.";
            return Response::html(
                $this->view->render('auth/login', ['notice' => null], 'Sign in', '', 'layout', ['email' => $email], ['_form' => $msg]),
                429,
            )->withHeader('Retry-After', (string) $e->retryAfter);
        } catch (InvalidCredentials $e) {
            return Response::html(
                $this->view->render('auth/login', ['notice' => null], 'Sign in', '', 'layout', ['email' => $email], ['_form' => $e->getMessage()]),
                401,
            );
        }

        $this->session->login((int) $user->id);
        return Response::redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        $this->audit->log($request->userId(), 'auth.logout', []);
        $this->session->destroy();
        $this->session->start();
        return Response::redirect('/login')->withFlash('success', 'You have been signed out.');
    }
}
