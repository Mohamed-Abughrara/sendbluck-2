<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Controller;

use MailDesk\Application\Configuration\{ConfigurationInput, SaveConfiguration, TestConfiguration};
use MailDesk\Domain\Configuration\MailConfigurationRepositoryInterface;
use MailDesk\Domain\Exception\ValidationFailed;
use MailDesk\Infrastructure\Session\NativeSession;
use MailDesk\Presentation\Http\{Request, Response, View};

final class ConfigurationController
{
    public function __construct(
        private MailConfigurationRepositoryInterface $configs,
        private SaveConfiguration $save,
        private TestConfiguration $test,
        private NativeSession $session,
        private View $view,
    ) {
    }

    public function show(Request $request, int $status = 200): Response
    {
        return $this->renderForm($status);
    }

    public function save(Request $request): Response
    {
        $input = $this->input($request);
        $fp = $this->session->get('cfg_test_fp');
        $at = $this->session->get('cfg_test_at');

        try {
            $config = $this->save->execute(
                $input,
                $request->userId(),
                is_string($fp) ? $fp : null,
                is_int($at) ? $at : null,
            );
        } catch (ValidationFailed $e) {
            return $this->renderForm(422, $request->post() ?? [], $e->errors());
        }

        $msg = $config->isVerified()
            ? 'Configuration saved and verified.'
            : 'Configuration saved, but it is not verified. Test the connection and save again to enable sending.';
        return Response::redirect('/configuration')->withFlash($config->isVerified() ? 'success' : 'warning', $msg);
    }

    public function test(Request $request): Response
    {
        try {
            $outcome = $this->test->execute($this->input($request), $request->userId());
        } catch (ValidationFailed $e) {
            return Response::json(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $e->errors()], 422);
        }

        if ($outcome->success) {
            $this->session->set('cfg_test_fp', $outcome->fingerprint);
            $this->session->set('cfg_test_at', time());
            return Response::json(['ok' => true, 'message' => 'Connection successful.']);
        }

        $this->session->remove('cfg_test_fp');
        $this->session->remove('cfg_test_at');
        return Response::json(['ok' => false, 'message' => $outcome->error ?? 'Connection failed.']);
    }

    private function input(Request $request): ConfigurationInput
    {
        $password = $request->str('smtp_password');
        return new ConfigurationInput(
            $request->str('sender_name'),
            $request->str('sender_email'),
            $request->str('smtp_host'),
            (int) $request->str('smtp_port', '587'),
            $request->str('encryption', 'tls'),
            $request->str('smtp_username'),
            $password === '' ? null : $password,
        );
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function renderForm(int $status = 200, array $old = [], array $errors = []): Response
    {
        $c = $this->configs->getActive();
        // View model: the password (or its ciphertext) is deliberately never passed to the template.
        $vm = [
            'exists' => $c !== null,
            'sender_name' => $c?->senderName ?? '',
            'sender_email' => $c !== null ? (string) $c->senderEmail : '',
            'smtp_host' => $c?->host ?? '',
            'smtp_port' => $c?->port ?? 587,
            'encryption' => $c?->encryption->value ?? 'tls',
            'smtp_username' => $c?->username ?? '',
            'verified' => $c?->isVerified() ?? false,
            'last_tested_at' => $c?->lastTestedAt,
        ];
        return Response::html(
            $this->view->render('configuration/index', ['cfg' => $vm], 'Configuration', 'configuration', 'layout', $old, $errors),
            $status,
        );
    }
}
