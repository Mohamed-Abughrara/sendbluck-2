<?php
declare(strict_types=1);

namespace MailDesk\Application\Configuration;

use MailDesk\Application\Port\{AuditLoggerInterface, ClockInterface, EncryptorInterface, MailerInterface};
use MailDesk\Domain\Configuration\MailConfigurationRepositoryInterface;
use MailDesk\Domain\Exception\ValidationFailed;

/** Tests SMTP credentials without sending mail. Never stores a new configuration. */
final class TestConfiguration
{
    public function __construct(
        private MailConfigurationRepositoryInterface $configs,
        private MailerInterface $mailer,
        private EncryptorInterface $encryptor,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
        private string $fingerprintKey,
    ) {
    }

    public function execute(ConfigurationInput $in, int $userId): TestOutcome
    {
        $stored = $this->configs->getActive();

        $plain = (string) $in->password;
        if ($plain === '') {
            if ($stored === null) {
                throw new ValidationFailed(['smtp_password' => 'Password is required.']);
            }
            $plain = $this->encryptor->decrypt($stored->encryptedPassword);
        }

        $config = ConfigurationFactory::build($in, '', false, null, null);
        $result = $this->mailer->testConnection($config, $plain);

        $fp = ConfigurationFingerprint::of($this->fingerprintKey, $config->host, $config->port, $config->encryption, $config->username, $plain);

        // If the tested values are exactly the stored ones, reflect the result on the stored row.
        if ($stored !== null) {
            $storedFp = ConfigurationFingerprint::of(
                $this->fingerprintKey,
                $stored->host,
                $stored->port,
                $stored->encryption,
                $stored->username,
                $this->encryptor->decrypt($stored->encryptedPassword),
            );
            if (hash_equals($storedFp, $fp)) {
                $this->configs->markTested($result->success, $this->clock->now()->format('Y-m-d H:i:s'));
            }
        }
        unset($plain);

        $this->audit->log($userId, 'config.tested', ['success' => $result->success]);

        return new TestOutcome($result->success, $result->error, $fp);
    }
}
