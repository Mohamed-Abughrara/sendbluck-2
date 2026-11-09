<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Http;

use MailDesk\Domain\User\UserRepositoryInterface;
use MailDesk\Infrastructure\Session\NativeSession;

/** Renders plain-PHP templates inside the shared layout. */
final class View
{
    private ?string $userName = null;

    /**
     * @param \Closure():string $configStatus
     * @param \Closure():UserRepositoryInterface $users
     */
    public function __construct(
        private string $dir,
        private NativeSession $session,
        private \Closure $configStatus,
        private \Closure $users,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    public function render(string $template, array $data = [], string $title = 'MailDesk', string $nav = '', ?string $layout = 'layout', array $old = [], array $errors = []): string
    {
        // inline values win; otherwise pick up what a previous redirect stored
        $sessOld = $this->session->get('_old');
        $sessErr = $this->session->get('_errors');
        $this->session->remove('_old');
        $this->session->remove('_errors');
        ViewContext::setOld($old !== [] ? $old : (is_array($sessOld) ? $sessOld : []));
        ViewContext::setErrors($errors !== [] ? $errors : (is_array($sessErr) ? $sessErr : []));

        $content = $this->capture($template, $data);
        if ($layout === null) {
            return $content;
        }

        $loggedIn = $this->session->userId() !== null;
        return $this->capture($layout, [
            'content' => $content,
            'title' => $title,
            'nav' => $nav,
            'loggedIn' => $loggedIn,
            'configStatus' => $loggedIn ? ($this->configStatus)() : 'none',
            'userName' => $loggedIn ? $this->userName() : null,
            'flash' => $this->session->pullFlash(),
        ]);
    }

    private function userName(): string
    {
        if ($this->userName === null) {
            $u = ($this->users)()->findById((int) $this->session->userId());
            $this->userName = $u?->name ?? 'User';
        }
        return $this->userName;
    }

    /** @param array<string,mixed> $data */
    private function capture(string $template, array $data): string
    {
        $file = $this->dir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
