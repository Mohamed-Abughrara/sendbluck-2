<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Controller;

use MailDesk\Application\Dashboard\GetDashboardStats;
use MailDesk\Presentation\Http\{Request, Response, View};

final class DashboardController
{
    public function __construct(private GetDashboardStats $stats, private View $view)
    {
    }

    public function redirect(Request $request): Response
    {
        return Response::redirect($request->session->validate() ? '/dashboard' : '/login');
    }

    public function index(Request $request): Response
    {
        try {
            $stats = $this->stats->execute();
            $error = null;
        } catch (\Throwable) {
            $stats = null;
            $error = 'Statistics are unavailable right now.';
        }
        return Response::html($this->view->render('dashboard/index', ['stats' => $stats, 'error' => $error], 'Dashboard', 'dashboard'));
    }

    public function stats(Request $request): Response
    {
        return Response::json($this->stats->execute()->toArray());
    }
}
