<?php
/** @var \MailDesk\Domain\Message\Message $message @var bool $canSend */
use MailDesk\Application\Mail\HtmlSanitizer;

$who = static fn (array $list): string => implode(', ', array_map(
    static fn (array $r): string => ($r['name'] !== '' ? $r['name'] . ' ' : '') . '<' . $r['email'] . '>',
    $list,
));
$srcdoc = '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/assets/css/message.css"></head><body>'
    . HtmlSanitizer::clean($message->bodyHtml) . '</body></html>';
?>
<article class="reading">
    <header class="reading-head">
        <h2><?= e($message->subject) ?></h2>
        <dl class="meta">
            <dt>To</dt><dd><?= e($who($message->to)) ?></dd>
            <?php if ($message->cc !== []) : ?><dt>Cc</dt><dd><?= e($who($message->cc)) ?></dd><?php endif; ?>
            <dt>Date</dt><dd><?= e($message->sentAt->format('d M Y H:i')) ?> UTC</dd>
            <dt>Status</dt><dd><span class="pill pill-<?= e($message->status->value) ?>"><?= e(ucfirst($message->status->value)) ?></span><?= $message->retried ? ' <span class="muted">(retried)</span>' : '' ?></dd>
        </dl>
        <?php if (err('_form')) : ?><p class="alert alert-error" role="alert"><?= e(err('_form')) ?></p><?php endif; ?>
        <?php if ($message->error !== null) : ?><p class="alert alert-error" role="alert">Error: <?= e($message->error) ?></p><?php endif; ?>
        <div class="reading-actions">
            <a class="btn" href="/mail/compose?forward=<?= e($message->id) ?>">Forward</a>
            <?php if ($message->status->value === 'failed' && !$message->retried) : ?>
                <form method="post" action="/mail/<?= e($message->id) ?>/retry" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary" <?= $canSend ? '' : 'disabled' ?> data-once>Retry</button>
                </form>
            <?php endif; ?>
        </div>
    </header>
    <iframe class="reading-body" title="Message body" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="<?= e($srcdoc) ?>"></iframe>
</article>
