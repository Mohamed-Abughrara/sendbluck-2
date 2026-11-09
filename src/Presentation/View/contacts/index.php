<?php
/** @var array{items:list<\MailDesk\Domain\Contact\Contact>,total:int,page:int,pages:int} $result @var string $q @var string $sort @var string $dir @var ?array{mode:string,id:int} $modal */
$ret = ['q' => $q, 'sort' => $sort === 'name' ? '' : $sort, 'dir' => $dir === 'asc' ? '' : $dir, 'page' => $result['page'] > 1 ? $result['page'] : ''];
$sortLink = static function (string $col, string $label) use ($q, $sort, $dir): string {
    $next = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $arrow = $sort === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    $aria = $sort === $col ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';
    return '<th scope="col" aria-sort="' . $aria . '"><a href="' . e(url_with('/contacts', ['q' => $q, 'sort' => $col, 'dir' => $next])) . '">' . e($label) . $arrow . '</a></th>';
};
$open = $modal !== null;
$isEdit = $open && $modal['mode'] === 'edit';
?>
<div class="page-head">
    <h1>Contacts</h1>
    <div class="actions">
        <form method="get" action="/contacts" role="search" class="search-form" id="contact-search">
            <label for="q" class="sr-only">Search name or email</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Search name or email..." maxlength="100">
            <?php if ($sort !== 'name') : ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
            <?php if ($dir !== 'asc') : ?><input type="hidden" name="dir" value="<?= e($dir) ?>"><?php endif; ?>
            <noscript><button class="btn" type="submit">Search</button></noscript>
        </form>
        <button type="button" class="btn btn-primary" id="add-contact-btn">+ Add contact</button>
    </div>
</div>

<section class="card">
<?php if ($result['items'] === []) : ?>
    <div class="empty">
        <?php if ($q !== '') : ?>
            <p>No contacts match '<?= e($q) ?>'.</p>
        <?php else : ?>
            <p><?= e(t('contacts.empty')) ?></p>
            <p><button type="button" class="btn btn-primary" data-open-add>Add contact</button></p>
        <?php endif; ?>
    </div>
<?php else : ?>
    <div class="table-wrap">
    <table class="table" id="contacts-table">
        <thead>
            <tr>
                <th scope="col" class="col-check"><input type="checkbox" id="select-all" aria-label="Select all contacts on this page"></th>
                <?= $sortLink('name', 'Name') ?>
                <?= $sortLink('email', 'Email') ?>
                <?= $sortLink('created', 'Created') ?>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($result['items'] as $c) : ?>
            <tr>
                <td class="col-check"><input type="checkbox" class="row-check" value="<?= e($c->id) ?>" aria-label="Select <?= e($c->name) ?>"></td>
                <td><?= e($c->name) ?></td>
                <td><?= e((string) $c->email) ?></td>
                <td><?= e(fmt_date($c->createdAt, 'd M')) ?></td>
                <td class="row-actions">
                    <button type="button" class="btn btn-sm" data-edit data-id="<?= e($c->id) ?>" data-name="<?= e($c->name) ?>" data-email="<?= e((string) $c->email) ?>">Edit</button>
                    <button type="button" class="btn btn-sm btn-danger" data-delete data-id="<?= e($c->id) ?>" data-name="<?= e($c->name) ?>" data-email="<?= e((string) $c->email) ?>">Delete</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div class="table-foot">
        <span><span id="selected-count">0</span> selected</span>
        <button type="button" class="btn" id="send-selected" disabled>Send to selected</button>
        <span class="spacer"></span>
        <span class="muted"><?= e($result['total']) ?> contact<?= $result['total'] === 1 ? '' : 's' ?> · 20 per page</span>
        <?= pagination('/contacts', ['q' => $q, 'sort' => $sort === 'name' ? '' : $sort, 'dir' => $dir === 'asc' ? '' : $dir], $result['page'], $result['pages']) ?>
    </div>
<?php endif; ?>
</section>

<!-- Add / Edit dialog -->
<dialog id="contact-dialog" aria-labelledby="contact-dialog-title"<?= $open ? ' data-open="1"' : '' ?>>
    <form method="post" action="<?= $isEdit ? '/contacts/' . e($modal['id']) : '/contacts' ?>" id="contact-form" novalidate>
        <?= csrf_field() ?>
        <?php foreach ($ret as $k => $v) : ?><input type="hidden" name="return_<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <h2 id="contact-dialog-title"><?= $isEdit ? 'Edit contact' : 'Add contact' ?></h2>
        <div class="field">
            <label for="contact-name">Name</label>
            <input type="text" id="contact-name" name="name" value="<?= e(old('name')) ?>" required maxlength="100"<?= err('name') ? ' aria-invalid="true" aria-describedby="err-name"' : '' ?>>
            <?= field_error('name') ?>
        </div>
        <div class="field">
            <label for="contact-email">Email</label>
            <input type="email" id="contact-email" name="email" value="<?= e(old('email')) ?>" required maxlength="254"<?= err('email') ? ' aria-invalid="true" aria-describedby="err-email"' : '' ?>>
            <?= field_error('email') ?>
        </div>
        <div class="dialog-actions">
            <button type="button" class="btn" data-close>Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</dialog>

<!-- Delete confirmation -->
<dialog id="delete-dialog" aria-labelledby="delete-dialog-title">
    <form method="post" action="" id="delete-form">
        <?= csrf_field() ?>
        <?php foreach ($ret as $k => $v) : ?><input type="hidden" name="return_<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <h2 id="delete-dialog-title">Delete contact</h2>
        <p id="delete-text"></p>
        <div class="dialog-actions">
            <button type="button" class="btn" data-close>Cancel</button>
            <button type="submit" class="btn btn-danger">Delete</button>
        </div>
    </form>
</dialog>
