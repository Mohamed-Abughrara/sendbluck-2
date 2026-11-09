<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Controller;

use MailDesk\Application\Contact\ListContacts;
use MailDesk\Application\Mail\{HtmlSanitizer, ListMessages, RetryMessage, SendMessage, SendMessageInput};
use MailDesk\Domain\Configuration\MailConfigurationRepositoryInterface;
use MailDesk\Domain\Contact\ContactRepositoryInterface;
use MailDesk\Domain\Exception\{ConfigurationNotVerified, NotFound, TooManyRecipients, ValidationFailed};
use MailDesk\Domain\Message\{Message, MessageStatus};
use MailDesk\Presentation\Http\{Request, Response, View};

final class MailController
{
    public function __construct(
        private SendMessage $sendMessage,
        private RetryMessage $retryMessage,
        private ListMessages $listMessages,
        private ListContacts $listContacts,
        private ContactRepositoryInterface $contacts,
        private MailConfigurationRepositoryInterface $configs,
        private View $view,
        private int $maxRecipients = 50,
    ) {
    }

    /** GET /mail */
    public function index(Request $request): Response
    {
        $id = (int) $request->query('id', 0);
        if ($id > 0) {
            return $this->show($request, $id);
        }
        return $this->renderMail($request, $this->folder($request), 'empty');
    }

    /** GET /mail/{id} */
    public function show(Request $request, ?int $id = null): Response
    {
        $id ??= (int) $request->params['id'];
        $message = $this->listMessages->find($id);
        if ($message === null) {
            throw new NotFound('Message');
        }
        return $this->renderMail($request, $message->status->value, 'reading', ['message' => $message]);
    }

    /** GET /mail/compose */
    public function compose(Request $request): Response
    {
        $compose = ['to' => $this->contactChips($request->intList('to')), 'cc' => [], 'subject' => '', 'body' => ''];

        $fwd = (int) $request->query('forward', 0);
        if ($fwd > 0 && ($m = $this->listMessages->find($fwd)) !== null) {
            $compose['subject'] = 'Fwd: ' . $m->subject;
            $compose['body'] = '<p></p><p>---------- Forwarded message ----------</p>' . HtmlSanitizer::clean($m->bodyHtml);
        }
        return $this->renderMail($request, $this->folder($request), 'compose', ['compose' => $compose]);
    }

    /** POST /mail/send */
    public function send(Request $request): Response
    {
        $to = $request->intList('to');
        $cc = $request->intList('cc');
        $subject = $request->str('subject');
        $body = $request->str('body');

        $input = new SendMessageInput($request->userId(), $to, $cc, $subject, $body);

        try {
            $out = $this->sendMessage->execute($input);
        } catch (ConfigurationNotVerified $e) {
            return $this->composeAgain($request, 409, ['_form' => $e->getMessage()], $to, $cc, $subject, $body);
        } catch (ValidationFailed | TooManyRecipients $e) {
            return $this->composeAgain($request, 422, $e->errors(), $to, $cc, $subject, $body);
        }

        return $this->afterDelivery($out->sent, $out->messageId, $out->error);
    }

    /** POST /mail/{id}/retry */
    public function retry(Request $request): Response
    {
        $id = (int) $request->params['id'];
        try {
            $out = $this->retryMessage->execute($request->userId(), $id);
        } catch (ConfigurationNotVerified $e) {
            $message = $this->listMessages->find($id);
            return $this->renderMail($request, 'failed', 'reading', ['message' => $message, 'errors' => ['_form' => $e->getMessage()]], 409);
        } catch (ValidationFailed $e) {
            return Response::redirect('/mail/' . $id)->withFlash('error', implode(' ', $e->errors()));
        }
        return $this->afterDelivery($out->sent, $out->messageId, $out->error);
    }

    private function afterDelivery(bool $sent, int $messageId, ?string $error): Response
    {
        return $sent
            ? Response::redirect('/mail?folder=sent&id=' . $messageId)->withFlash('success', 'Message sent.')
            : Response::redirect('/mail?folder=failed&id=' . $messageId)->withFlash('error', 'Sending failed: ' . $error);
    }

    /**
     * @param array<string,string> $errors
     * @param list<int> $to
     * @param list<int> $cc
     */
    private function composeAgain(Request $request, int $status, array $errors, array $to, array $cc, string $subject, string $body): Response
    {
        $compose = [
            'to' => $this->contactChips($to),
            'cc' => $this->contactChips($cc),
            'subject' => $subject,
            'body' => HtmlSanitizer::clean($body),
        ];
        return $this->renderMail($request, 'sent', 'compose', ['compose' => $compose, 'errors' => $errors], $status);
    }

    /**
     * @param list<int> $ids
     * @return list<array{id:int,name:string,email:string}>
     */
    private function contactChips(array $ids): array
    {
        $chips = [];
        foreach (array_slice($ids, 0, $this->maxRecipients) as $id) {
            $c = $this->contacts->findById($id);
            if ($c !== null) {
                $chips[] = ['id' => (int) $c->id, 'name' => $c->name, 'email' => (string) $c->email];
            }
        }
        return $chips;
    }

    private function folder(Request $request): string
    {
        return $request->str('folder') === 'failed' ? 'failed' : 'sent';
    }

    /** @param array<string,mixed> $extra */
    private function renderMail(Request $request, string $folder, string $pane, array $extra = [], int $status = 200): Response
    {
        $q = mb_substr($request->str('q'), 0, 100);
        $list = $this->listMessages->execute(MessageStatus::from($folder), $q, (int) $request->query('page', 1), 25);
        $config = $this->configs->getActive();
        $errors = $extra['errors'] ?? [];
        unset($extra['errors']);

        $data = $extra + [
            'folder' => $folder,
            'pane' => $pane,
            'list' => $list,
            'counts' => $this->listMessages->counts(),
            'q' => $q,
            'message' => null,
            'compose' => ['to' => [], 'cc' => [], 'subject' => '', 'body' => ''],
            'canSend' => $config !== null && $config->isVerified(),
            'maxRecipients' => $this->maxRecipients,
        ];
        $data['activeId'] = $data['message'] instanceof Message ? $data['message']->id : null;

        return Response::html($this->view->render('mail/index', $data, 'Mail', 'mail', 'layout', [], $errors), $status);
    }
}
