<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Controller;

use MailDesk\Application\Contact\{AddContact, ContactInput, DeleteContact, ListContacts, UpdateContact};
use MailDesk\Domain\Exception\{DuplicateContact, NotFound, ValidationFailed};
use MailDesk\Presentation\Http\{Request, Response, View};

final class ContactController
{
    public function __construct(
        private AddContact $add,
        private UpdateContact $update,
        private DeleteContact $delete,
        private ListContacts $list,
        private View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->render($request);
    }

    public function store(Request $request): Response
    {
        try {
            $this->add->execute($this->input($request), $request->userId());
        } catch (ValidationFailed $e) {
            return $this->render($request, 422, ['mode' => 'add', 'id' => 0], $request->post() ?? [], $e->errors());
        } catch (DuplicateContact $e) {
            return $this->render($request, 422, ['mode' => 'add', 'id' => 0], $request->post() ?? [], ['email' => $e->getMessage()]);
        }
        return Response::redirect($this->back($request))->withFlash('success', 'Contact added.');
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->params['id'];
        try {
            $this->update->execute($id, $this->input($request), $request->userId());
        } catch (NotFound) {
            return Response::redirect($this->back($request))->withFlash('error', 'Contact no longer exists.');
        } catch (ValidationFailed $e) {
            return $this->render($request, 422, ['mode' => 'edit', 'id' => $id], $request->post() ?? [], $e->errors());
        } catch (DuplicateContact $e) {
            return $this->render($request, 422, ['mode' => 'edit', 'id' => $id], $request->post() ?? [], ['email' => $e->getMessage()]);
        }
        return Response::redirect($this->back($request))->withFlash('success', 'Contact updated.');
    }

    public function destroy(Request $request): Response
    {
        try {
            $this->delete->execute((int) $request->params['id'], $request->userId());
        } catch (NotFound) {
            return Response::redirect($this->back($request))->withFlash('error', 'Contact no longer exists.');
        }
        return Response::redirect($this->back($request))->withFlash('success', 'Contact deleted.');
    }

    public function search(Request $request): Response
    {
        $q = mb_substr($request->str('q'), 0, 100);
        if ($q === '') {
            return Response::json([]);
        }
        return Response::json(array_map(
            static fn ($c): array => ['id' => $c->id, 'name' => $c->name, 'email' => (string) $c->email],
            $this->list->suggest($q, 10),
        ));
    }

    private function input(Request $request): ContactInput
    {
        return new ContactInput($request->str('name'), $request->str('email'));
    }

    /** Return to the same list view (keeps search/sort/page) after a mutation. */
    private function back(Request $request): string
    {
        $qs = array_filter([
            'q' => $request->str('return_q'),
            'sort' => $request->str('return_sort'),
            'dir' => $request->str('return_dir'),
            'page' => $request->str('return_page'),
        ], static fn (string $v): bool => $v !== '');
        return '/contacts' . ($qs === [] ? '' : '?' . http_build_query($qs));
    }

    /**
     * @param array{mode:string,id:int}|null $modal
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function render(Request $request, int $status = 200, ?array $modal = null, array $old = [], array $errors = []): Response
    {
        $q = mb_substr($request->str('q', $request->str('return_q')), 0, 100);
        $sort = $request->str('sort', $request->str('return_sort'));
        $dir = $request->str('dir', $request->str('return_dir'));
        $page = (int) ($request->str('page', $request->str('return_page', '1')) ?: 1);
        $result = $this->list->execute($q, $sort, $dir, $page, 20);

        return Response::html($this->view->render('contacts/index', [
            'result' => $result,
            'q' => $q,
            'sort' => in_array($sort, ListContacts::SORTS, true) ? $sort : 'name',
            'dir' => strtolower($dir) === 'desc' ? 'desc' : 'asc',
            'modal' => $modal,
        ], 'Contacts', 'contacts', 'layout', $old, $errors), $status);
    }
}
