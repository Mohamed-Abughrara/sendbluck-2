<?php
/** @var array $compose @var bool $canSend @var int $maxRecipients */
$chips = static function (string $name, array $items): string {
    $out = '';
    foreach ($items as $c) {
        $out .= '<span class="chip" data-id="' . e($c['id']) . '"><span class="chip-label">' . e($c['name']) . '</span>'
            . '<input type="hidden" name="' . e($name) . '[]" value="' . e($c['id']) . '">'
            . '<button type="button" class="chip-x" aria-label="Remove ' . e($c['name']) . '">&times;</button></span>';
    }
    return $out;
};
?>
<form method="post" action="/mail/send" id="compose-form" class="compose" novalidate
      data-max="<?= e($maxRecipients) ?>" data-can-send="<?= $canSend ? '1' : '0' ?>">
    <?= csrf_field() ?>
    <h2 class="sr-only">New message</h2>

    <?php if (!$canSend) : ?>
        <p class="alert alert-warn" role="alert"><?= e(t('mail.disabled_banner')) ?> <a href="/configuration">Open Configuration</a></p>
    <?php endif; ?>
    <?php if (err('_form')) : ?><p class="alert alert-error" role="alert"><?= e(err('_form')) ?></p><?php endif; ?>

    <?php foreach (['to' => 'To', 'cc' => 'Cc'] as $name => $label) : ?>
        <div class="field field-inline">
            <label for="<?= e($name) ?>-input" id="<?= e($name) ?>-label"><?= e($label) ?></label>
            <div class="chips-field" data-name="<?= e($name) ?>">
                <div class="chips"><?= $chips($name, $compose[$name]) ?></div>
                <input type="text" id="<?= e($name) ?>-input" class="chip-input" autocomplete="off" placeholder="Type a name or email..."
                       role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="<?= e($name) ?>-list"
                       <?= err($name) ? 'aria-invalid="true" aria-describedby="err-' . e($name) . '"' : '' ?>>
                <ul class="suggestions" id="<?= e($name) ?>-list" role="listbox" aria-labelledby="<?= e($name) ?>-label" hidden></ul>
            </div>
        </div>
        <?= field_error($name) ?>
    <?php endforeach; ?>

    <div class="field field-inline">
        <label for="subject">Subject</label>
        <input type="text" id="subject" name="subject" value="<?= e($compose['subject']) ?>" required maxlength="200"<?= err('subject') ? ' aria-invalid="true" aria-describedby="err-subject"' : '' ?>>
    </div>
    <?= field_error('subject') ?>

    <div class="toolbar" role="toolbar" aria-label="Text formatting">
        <button type="button" class="btn btn-sm" data-cmd="bold" aria-label="Bold"><b>B</b></button>
        <button type="button" class="btn btn-sm" data-cmd="italic" aria-label="Italic"><i>I</i></button>
        <button type="button" class="btn btn-sm" data-cmd="underline" aria-label="Underline"><u>U</u></button>
        <button type="button" class="btn btn-sm" data-cmd="insertUnorderedList" aria-label="Bulleted list">&bull; List</button>
        <button type="button" class="btn btn-sm" data-cmd="insertOrderedList" aria-label="Numbered list">1. List</button>
        <button type="button" class="btn btn-sm" data-cmd="createLink" aria-label="Insert link">Link</button>
    </div>
    <div id="editor" class="editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Message body" tabindex="0"><?= $compose['body'] /* sanitised server-side */ ?></div>
    <textarea name="body" id="body" hidden></textarea>
    <?= field_error('body') ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary" id="send-btn" <?= $canSend ? '' : 'disabled' ?>>Send</button>
        <button type="button" class="btn" id="discard-btn">Discard</button>
        <span class="muted" id="send-status" role="status" aria-live="polite"></span>
    </div>
</form>
