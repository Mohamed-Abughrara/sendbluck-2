<?php
/**
 * @var string $folder @var string $pane @var array $list @var array{sent:int,failed:int} $counts @var string $q
 * @var ?\MailDesk\Domain\Message\Message $message @var array $compose @var bool $canSend @var int $maxRecipients @var ?int $activeId
 */
$mobile = $pane === 'empty' ? 'list' : 'pane';
?>
<div class="mail-layout" data-mobile="<?= e($mobile) ?>">
    <aside class="mail-folders" aria-label="Folders">
        <a class="btn btn-primary btn-block" href="/mail/compose?folder=<?= e($folder) ?>">+ New message</a>
        <nav>
            <a href="/mail?folder=sent"<?= $folder === 'sent' ? ' aria-current="page" class="active"' : '' ?>>
                <span>Sent</span><span class="count"><?= e($counts['sent']) ?></span>
            </a>
            <a href="/mail?folder=failed"<?= $folder === 'failed' ? ' aria-current="page" class="active"' : '' ?>>
                <span>Failed</span><span class="count<?= $counts['failed'] > 0 ? ' count-bad' : '' ?>"><?= e($counts['failed']) ?></span>
            </a>
        </nav>
    </aside>

    <section class="mail-list" aria-label="<?= e(ucfirst($folder)) ?> messages">
        <form method="get" action="/mail" role="search" class="mail-search">
            <input type="hidden" name="folder" value="<?= e($folder) ?>">
            <label for="mail-q" class="sr-only">Search mail</label>
            <input type="search" id="mail-q" name="q" value="<?= e($q) ?>" placeholder="Search mail..." maxlength="100">
        </form>
        <?php if ($list['items'] === []) : ?>
            <p class="empty"><?= e($q !== '' ? "No messages match '" . $q . "'." : t('mail.empty.' . $folder)) ?></p>
        <?php else : ?>
            <ul class="msg-list">
                <?php foreach ($list['items'] as $m) : ?>
                    <li>
                        <a href="/mail/<?= e($m->id) ?>"<?= $activeId === $m->id ? ' aria-current="true" class="active"' : '' ?>>
                            <span class="msg-row-top">
                                <span class="msg-to"><?= e(recipients_label($m->to, $m->cc)) ?></span>
                                <span class="msg-date"><?= e($m->sentAt->format('d M H:i')) ?></span>
                            </span>
                            <span class="msg-row-bottom">
                                <span class="msg-subject"><?= e($m->subject) ?></span>
                                <span class="pill pill-<?= e($m->status->value) ?>"><?= e(ucfirst($m->status->value)) ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?= pagination('/mail', ['folder' => $folder, 'q' => $q], $list['page'], $list['pages']) ?>
        <?php endif; ?>
    </section>

    <section class="mail-pane" aria-label="Message">
        <a class="back-link" href="/mail?folder=<?= e($folder) ?>">&larr; Back to <?= e($folder) ?></a>

        <?php if ($pane === 'compose') : ?>
            <?php include __DIR__ . '/_compose.php'; ?>
        <?php elseif ($pane === 'reading' && $message !== null) : ?>
            <?php include __DIR__ . '/_reading.php'; ?>
        <?php else : ?>
            <p class="empty pane-empty"><?= e(t('mail.select')) ?></p>
        <?php endif; ?>
    </section>
</div>
