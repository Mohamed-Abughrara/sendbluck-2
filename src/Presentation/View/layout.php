<?php
/** @var string $content @var string $title @var string $nav @var bool $loggedIn @var string $configStatus @var ?string $userName @var array $flash */
$badge = ['verified' => 'ok', 'unverified' => 'warn', 'none' => 'bad'][$configStatus] ?? 'bad';
$navItems = ['dashboard' => '/dashboard', 'mail' => '/mail', 'contacts' => '/contacts', 'configuration' => '/configuration'];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> – MailDesk</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="<?= $loggedIn ? 'app' : 'guest' ?>">
<a class="skip-link" href="#main">Skip to content</a>

<?php if ($loggedIn) : ?>
<header class="topbar">
    <a class="logo" href="/dashboard">MailDesk</a>
    <a class="badge badge-<?= e($badge) ?>" href="/configuration" title="Mail configuration status">
        <span class="dot" aria-hidden="true"></span><?= e(t('status.' . $configStatus)) ?>
    </a>
    <div class="spacer"></div>
    <details class="user-menu">
        <summary><?= e($userName ?? 'User') ?></summary>
        <form method="post" action="/logout">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-link"><?= e(t('nav.logout')) ?></button>
        </form>
    </details>
</header>
<div class="shell">
    <nav class="sidenav" aria-label="Main">
        <?php foreach ($navItems as $key => $href) : ?>
            <a href="<?= e($href) ?>"<?= $nav === $key ? ' aria-current="page" class="active"' : '' ?>>
                <span class="nav-icon nav-<?= e($key) ?>" aria-hidden="true"></span><span class="nav-label"><?= e(t('nav.' . $key)) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <main id="main" class="content" tabindex="-1">
        <?= $content ?>
    </main>
</div>
<?php else : ?>
<main id="main" class="guest-main" tabindex="-1">
    <?= $content ?>
</main>
<?php endif; ?>

<div id="toasts" class="toasts" role="status" aria-live="polite" aria-atomic="false">
    <?php foreach ($flash as $f) : ?>
        <div class="toast toast-<?= e($f['type']) ?>" data-type="<?= e($f['type']) ?>">
            <span><?= e($f['message']) ?></span>
            <button type="button" class="toast-close" aria-label="Dismiss notification">&times;</button>
        </div>
    <?php endforeach; ?>
</div>
<script src="/assets/js/app.js" defer></script>
<?php if ($loggedIn && $nav === 'mail') : ?><script src="/assets/js/compose.js" defer></script><?php endif; ?>
<?php if ($loggedIn && $nav === 'contacts') : ?><script src="/assets/js/contacts.js" defer></script><?php endif; ?>
<?php if ($loggedIn && $nav === 'configuration') : ?><script src="/assets/js/configuration.js" defer></script><?php endif; ?>
</body>
</html>
