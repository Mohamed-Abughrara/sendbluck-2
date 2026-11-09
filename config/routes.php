<?php
declare(strict_types=1);

use MailDesk\Presentation\Controller\{AuthController, ConfigurationController, ContactController, DashboardController, MailController};

/**
 * [method, path, [Controller::class, 'action'], options]
 * options: auth (bool, default true), csrf (bool), rate (bucket name)
 */
return [
    ['GET', '/', [DashboardController::class, 'redirect'], ['auth' => false]],
    ['GET', '/login', [AuthController::class, 'showLogin'], ['auth' => false]],
    ['POST', '/login', [AuthController::class, 'login'], ['auth' => false, 'csrf' => true]],
    ['POST', '/logout', [AuthController::class, 'logout'], ['csrf' => true]],

    ['GET', '/dashboard', [DashboardController::class, 'index'], []],
    ['GET', '/api/dashboard/stats', [DashboardController::class, 'stats'], []],

    ['GET', '/configuration', [ConfigurationController::class, 'show'], []],
    ['POST', '/configuration', [ConfigurationController::class, 'save'], ['csrf' => true]],
    ['POST', '/api/configuration/test', [ConfigurationController::class, 'test'], ['csrf' => true, 'rate' => 'test']],

    ['GET', '/contacts', [ContactController::class, 'index'], []],
    ['POST', '/contacts', [ContactController::class, 'store'], ['csrf' => true]],
    ['POST', '/contacts/{id:\d+}', [ContactController::class, 'update'], ['csrf' => true]],
    ['POST', '/contacts/{id:\d+}/delete', [ContactController::class, 'destroy'], ['csrf' => true]],
    ['GET', '/api/contacts/search', [ContactController::class, 'search'], ['rate' => 'search']],

    ['GET', '/mail', [MailController::class, 'index'], []],
    ['GET', '/mail/compose', [MailController::class, 'compose'], []],
    ['POST', '/mail/send', [MailController::class, 'send'], ['csrf' => true, 'rate' => 'send']],
    ['GET', '/mail/{id:\d+}', [MailController::class, 'show'], []],
    ['POST', '/mail/{id:\d+}/retry', [MailController::class, 'retry'], ['csrf' => true, 'rate' => 'send']],
];
