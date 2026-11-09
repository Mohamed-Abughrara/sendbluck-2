<?php
/** @var array<string,mixed> $cfg */
$val = static fn (string $k): string => (string) old($k, $cfg[$k] ?? '');
$enc = (string) old('encryption', $cfg['encryption']);
$invalid = static fn (string $k): string => err($k) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
?>
<div class="page-head">
    <h1>Configuration</h1>
    <p class="status-line" id="cfg-status">
        <?php if (!$cfg['exists']) : ?>
            Status: <span class="pill pill-failed">Not configured</span>
        <?php elseif ($cfg['verified']) : ?>
            Status: <span class="pill pill-sent">Verified</span> <span class="muted">Last tested: <?= e(fmt_date($cfg['last_tested_at'], 'd M Y H:i')) ?> UTC – Success</span>
        <?php else : ?>
            Status: <span class="pill pill-warn">Not verified</span>
        <?php endif; ?>
    </p>
</div>

<?php if (!$cfg['exists']) : ?>
    <p class="alert alert-info"><?= e(t('config.empty')) ?></p>
<?php endif; ?>

<form method="post" action="/configuration" class="card form-grid" id="config-form" novalidate autocomplete="off">
    <?= csrf_field() ?>

    <div class="field">
        <label for="sender_name">Sender name</label>
        <input type="text" id="sender_name" name="sender_name" value="<?= e($val('sender_name')) ?>" required maxlength="100"<?= $invalid('sender_name') ?>>
        <?= field_error('sender_name') ?>
    </div>
    <div class="field">
        <label for="sender_email">Sender email</label>
        <input type="email" id="sender_email" name="sender_email" value="<?= e($val('sender_email')) ?>" required maxlength="254"<?= $invalid('sender_email') ?>>
        <?= field_error('sender_email') ?>
    </div>
    <div class="field">
        <label for="smtp_host">SMTP host</label>
        <input type="text" id="smtp_host" name="smtp_host" value="<?= e($val('smtp_host')) ?>" required maxlength="255" placeholder="smtp.example.org"<?= $invalid('smtp_host') ?>>
        <?= field_error('smtp_host') ?>
    </div>
    <div class="field-row">
        <div class="field">
            <label for="smtp_port">Port</label>
            <input type="number" id="smtp_port" name="smtp_port" value="<?= e($val('smtp_port')) ?>" required min="1" max="65535"<?= $invalid('smtp_port') ?>>
            <?= field_error('smtp_port') ?>
        </div>
        <div class="field">
            <label for="encryption">Encryption</label>
            <select id="encryption" name="encryption"<?= $invalid('encryption') ?>>
                <?php foreach (['tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL (SMTPS)', 'none' => 'None'] as $k => $label) : ?>
                    <option value="<?= e($k) ?>"<?= $enc === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error('encryption') ?>
        </div>
    </div>
    <p class="hint" id="enc-hint" hidden></p>
    <p class="alert alert-warn" id="enc-warning" role="alert" hidden>Credentials will be sent unencrypted.</p>

    <div class="field">
        <label for="smtp_username">Username</label>
        <input type="text" id="smtp_username" name="smtp_username" value="<?= e($val('smtp_username')) ?>" required maxlength="254" autocomplete="off"<?= $invalid('smtp_username') ?>>
        <?= field_error('smtp_username') ?>
    </div>
    <div class="field">
        <label for="smtp_password">Password</label>
        <!-- Never pre-filled: the saved password is not sent to the browser. -->
        <input type="password" id="smtp_password" name="smtp_password" value="" maxlength="256" autocomplete="new-password"
               placeholder="<?= $cfg['exists'] ? '•••••• (saved)' : '' ?>"<?= $cfg['exists'] ? '' : ' required' ?><?= $invalid('smtp_password') ?>>
        <?= field_error('smtp_password') ?>
        <?php if ($cfg['exists']) : ?><p class="hint">Leave empty to keep the saved password.</p><?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="button" class="btn" id="test-btn" data-url="/api/configuration/test">Test connection</button>
        <a class="btn" href="/configuration">Reset</a>
        <span class="spacer"></span>
        <button type="submit" class="btn btn-primary">Save</button>
    </div>
    <p id="test-result" class="test-result" role="status" aria-live="polite"></p>
</form>
