# MailDesk

Internal contact mailer, built from `MailDesk_PDR.md` (PHP 8.2+, Clean Architecture, PDO, PHPMailer, libsodium).

## Quick start (XAMPP / Windows)

1. Enable `extension=sodium` in `php.ini` (and `pdo_sqlite` or `pdo_mysql`).
2. `composer install`
3. `php bin/console key:generate` (creates `.env` from `.env.example`). For local use set `APP_ENV=local` so HTTPS is not enforced.
4. `php bin/console migrate`
5. `php bin/console user:create` (password at least 12 characters)
6. `php -S localhost:8080 -t public`, then open http://localhost:8080

Under Apache, point the vhost document root at `public/` (the app uses root-relative URLs, so it can't run from a sub-folder).

Database defaults to SQLite (`storage/maildesk.sqlite`). For MySQL set `DB_DRIVER=mysql` and the `DB_*` values in `.env`, then run `migrate`.

## Using it
1. **Configuration**: enter the SMTP account, **Test connection**, **Save**. Sending stays disabled until the saved values match a successful test.
2. **Contacts**: add recipients. 3. **Mail**: compose, pick recipients from contacts, send.

Back up `APP_KEY`: without it the saved SMTP password can't be decrypted.

## Tests
`vendor\bin\phpunit` (unit + SQLite integration).

## Layout
`src/Domain`, `src/Application`, `src/Infrastructure`, `src/Presentation` (dependencies point inward). Routes: `config/routes.php`. Migrations: `database/migrations`.
