<?php /** @var ?string $notice */ ?>
<section class="login-card card">
    <h1 class="login-title">MailDesk</h1>
    <?php if (!empty($notice)) : ?><p class="alert alert-info" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if (err('_form')) : ?><p class="alert alert-error" role="alert"><?= e(err('_form')) ?></p><?php endif; ?>

    <form method="post" action="/login" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" required maxlength="254" autocomplete="username" autofocus<?= err('email') ? ' aria-invalid="true" aria-describedby="err-email"' : '' ?>>
            <?= field_error('email') ?>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required maxlength="128" autocomplete="current-password"<?= err('password') ? ' aria-invalid="true" aria-describedby="err-password"' : '' ?>>
            <?= field_error('password') ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
    </form>
</section>
