<?php
declare(strict_types=1);

use MailDesk\Application\Auth\Authenticate;
use MailDesk\Application\Configuration\{SaveConfiguration, TestConfiguration};
use MailDesk\Application\Contact\{AddContact, DeleteContact, ListContacts, UpdateContact};
use MailDesk\Application\Dashboard\GetDashboardStats;
use MailDesk\Application\Mail\{ListMessages, RetryMessage, SendMessage};
use MailDesk\Application\Port\{AuditLoggerInterface, ClockInterface, EncryptorInterface, MailerInterface, PasswordHasherInterface, RateLimiterInterface};
use MailDesk\Domain\Configuration\MailConfigurationRepositoryInterface;
use MailDesk\Domain\Contact\ContactRepositoryInterface;
use MailDesk\Domain\Message\MessageRepositoryInterface;
use MailDesk\Domain\User\UserRepositoryInterface;
use MailDesk\Infrastructure\Container\Container;
use MailDesk\Infrastructure\Logging\{FileErrorLogger, PdoAuditLogger};
use MailDesk\Infrastructure\Mail\PHPMailerAdapter;
use MailDesk\Infrastructure\Persistence\{PdoContactRepository, PdoFactory, PdoMailConfigurationRepository, PdoMessageRepository, PdoUserRepository};
use MailDesk\Infrastructure\Security\{CsrfTokenManager, NativePasswordHasher, PdoRateLimiter, SodiumEncryptor, SystemClock};
use MailDesk\Infrastructure\Session\NativeSession;
use MailDesk\Presentation\Controller\{AuthController, ConfigurationController, ContactController, DashboardController, MailController};
use MailDesk\Presentation\Http\{Kernel, View};

return static function (Container $c, array $settings): void {
    $c->singleton('settings', fn () => $settings);

    // Infrastructure
    $c->singleton(PDO::class, fn () => PdoFactory::create($settings['db']));
    $c->singleton(FileErrorLogger::class, fn () => new FileErrorLogger($settings['root'] . '/storage/logs/app.log', (string) $settings['log_level']));
    $c->singleton(EncryptorInterface::class, fn () => new SodiumEncryptor($settings['app_key']));
    $c->singleton(PasswordHasherInterface::class, fn () => new NativePasswordHasher());
    $c->singleton(MailerInterface::class, fn () => new PHPMailerAdapter($settings['mail_timeout']));
    $c->singleton(ClockInterface::class, fn () => new SystemClock());
    $c->singleton(ContactRepositoryInterface::class, fn ($c) => new PdoContactRepository($c->get(PDO::class)));
    $c->singleton(MailConfigurationRepositoryInterface::class, fn ($c) => new PdoMailConfigurationRepository($c->get(PDO::class)));
    $c->singleton(MessageRepositoryInterface::class, fn ($c) => new PdoMessageRepository($c->get(PDO::class)));
    $c->singleton(UserRepositoryInterface::class, fn ($c) => new PdoUserRepository($c->get(PDO::class)));
    $c->singleton(AuditLoggerInterface::class, fn ($c) => new PdoAuditLogger($c->get(PDO::class), $c->get(FileErrorLogger::class)));
    $c->singleton(RateLimiterInterface::class, fn ($c) => new PdoRateLimiter($c->get(PDO::class)));
    $c->singleton(NativeSession::class, fn () => new NativeSession($settings['is_production'], $settings['session_idle'], $settings['session_absolute']));
    $c->singleton(CsrfTokenManager::class, fn ($c) => new CsrfTokenManager($c->get(NativeSession::class)));

    // Application
    $c->bind(Authenticate::class, fn ($c) => new Authenticate(
        $c->get(UserRepositoryInterface::class),
        $c->get(PasswordHasherInterface::class),
        $c->get(RateLimiterInterface::class),
        $c->get(AuditLoggerInterface::class),
        $c->get(ClockInterface::class),
        $settings['rate_login_max'],
        $settings['rate_login_window'],
    ));
    $c->bind(TestConfiguration::class, fn ($c) => new TestConfiguration(
        $c->get(MailConfigurationRepositoryInterface::class),
        $c->get(MailerInterface::class),
        $c->get(EncryptorInterface::class),
        $c->get(AuditLoggerInterface::class),
        $c->get(ClockInterface::class),
        $settings['app_key'],
    ));
    $c->bind(SaveConfiguration::class, fn ($c) => new SaveConfiguration(
        $c->get(MailConfigurationRepositoryInterface::class),
        $c->get(EncryptorInterface::class),
        $c->get(AuditLoggerInterface::class),
        $c->get(ClockInterface::class),
        $settings['app_key'],
    ));
    $c->bind(AddContact::class, fn ($c) => new AddContact($c->get(ContactRepositoryInterface::class), $c->get(AuditLoggerInterface::class), $c->get(ClockInterface::class)));
    $c->bind(UpdateContact::class, fn ($c) => new UpdateContact($c->get(ContactRepositoryInterface::class), $c->get(AuditLoggerInterface::class), $c->get(ClockInterface::class)));
    $c->bind(DeleteContact::class, fn ($c) => new DeleteContact($c->get(ContactRepositoryInterface::class), $c->get(AuditLoggerInterface::class), $c->get(ClockInterface::class)));
    $c->bind(ListContacts::class, fn ($c) => new ListContacts($c->get(ContactRepositoryInterface::class)));
    $c->bind(SendMessage::class, fn ($c) => new SendMessage(
        $c->get(MailConfigurationRepositoryInterface::class),
        $c->get(ContactRepositoryInterface::class),
        $c->get(MessageRepositoryInterface::class),
        $c->get(MailerInterface::class),
        $c->get(EncryptorInterface::class),
        $c->get(AuditLoggerInterface::class),
        $c->get(ClockInterface::class),
        maxRecipients: $settings['mail_max_recipients'],
    ));
    $c->bind(RetryMessage::class, fn ($c) => new RetryMessage($c->get(MessageRepositoryInterface::class), $c->get(SendMessage::class)));
    $c->bind(ListMessages::class, fn ($c) => new ListMessages($c->get(MessageRepositoryInterface::class)));
    $c->bind(GetDashboardStats::class, fn ($c) => new GetDashboardStats(
        $c->get(ContactRepositoryInterface::class),
        $c->get(MessageRepositoryInterface::class),
        $c->get(MailConfigurationRepositoryInterface::class),
    ));

    // Presentation
    $c->singleton(View::class, fn ($c) => new View(
        $settings['root'] . '/src/Presentation/View',
        $c->get(NativeSession::class),
        fn () => $c->get(GetDashboardStats::class)->configStatus(),
        fn () => $c->get(UserRepositoryInterface::class),
    ));
    $c->bind(AuthController::class, fn ($c) => new AuthController($c->get(Authenticate::class), $c->get(NativeSession::class), $c->get(AuditLoggerInterface::class), $c->get(View::class)));
    $c->bind(DashboardController::class, fn ($c) => new DashboardController($c->get(GetDashboardStats::class), $c->get(View::class)));
    $c->bind(ConfigurationController::class, fn ($c) => new ConfigurationController(
        $c->get(MailConfigurationRepositoryInterface::class),
        $c->get(SaveConfiguration::class),
        $c->get(TestConfiguration::class),
        $c->get(NativeSession::class),
        $c->get(View::class),
    ));
    $c->bind(ContactController::class, fn ($c) => new ContactController(
        $c->get(AddContact::class),
        $c->get(UpdateContact::class),
        $c->get(DeleteContact::class),
        $c->get(ListContacts::class),
        $c->get(View::class),
    ));
    $c->bind(MailController::class, fn ($c) => new MailController(
        $c->get(SendMessage::class),
        $c->get(RetryMessage::class),
        $c->get(ListMessages::class),
        $c->get(ListContacts::class),
        $c->get(ContactRepositoryInterface::class),
        $c->get(MailConfigurationRepositoryInterface::class),
        $c->get(View::class),
        $settings['mail_max_recipients'],
    ));
    $c->singleton(Kernel::class, fn ($c) => new Kernel($c, $settings));
};
