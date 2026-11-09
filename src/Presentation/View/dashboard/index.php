<?php /** @var ?\MailDesk\Application\Dashboard\DashboardStats $stats @var ?string $error */ ?>
<div class="page-head">
    <h1>Dashboard</h1>
    <div class="actions">
        <?php if ($stats && $stats->configStatus !== 'verified') : ?>
            <a class="btn" href="/configuration">Go to Configuration</a>
        <?php endif; ?>
        <a class="btn btn-primary" href="/mail/compose">+ New message</a>
    </div>
</div>

<?php if ($error) : ?>
    <p class="alert alert-error" role="alert"><?= e($error) ?></p>
<?php elseif ($stats) : ?>
    <section class="kpis" aria-label="Key figures">
        <div class="kpi"><span class="kpi-label">Total contacts</span><span class="kpi-value"><?= e($stats->contacts) ?></span></div>
        <div class="kpi"><span class="kpi-label">Emails sent</span><span class="kpi-value"><?= e($stats->sent) ?></span></div>
        <div class="kpi"><span class="kpi-label">Failed</span><span class="kpi-value<?= $stats->failed > 0 ? ' text-bad' : '' ?>"><?= e($stats->failed) ?></span></div>
        <div class="kpi"><span class="kpi-label">Last sent</span><span class="kpi-value kpi-small"><?= e(fmt_date($stats->lastSent, 'd M H:i')) ?></span></div>
    </section>

    <?php if ($stats->configStatus === 'none') : ?>
        <p class="alert alert-info"><?= e(t('config.empty')) ?></p>
    <?php endif; ?>

    <section class="card">
        <h2>Recent messages</h2>
        <?php if ($stats->recent === []) : ?>
            <div class="empty">
                <p><?= e(t('dashboard.empty')) ?></p>
                <p><a class="btn" href="/contacts">Add contact</a> <a class="btn btn-primary" href="/mail/compose">New message</a></p>
            </div>
        <?php else : ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">Date</th><th scope="col">Recipients</th><th scope="col">Subject</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($stats->recent as $m) : ?>
                        <tr>
                            <td><?= e($m->sentAt->format('d M H:i')) ?></td>
                            <td><?= e(recipients_label($m->to, $m->cc)) ?></td>
                            <td><a href="/mail/<?= e($m->id) ?>"><?= e($m->subject) ?></a></td>
                            <td><span class="pill pill-<?= e($m->status->value) ?>"><?= e(ucfirst($m->status->value)) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
