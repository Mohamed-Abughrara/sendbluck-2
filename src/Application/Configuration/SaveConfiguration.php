<?php
declare(strict_types=1);

namespace MailDesk\Application\Configuration;

use MailDesk\Application\Port\{AuditLoggerInterface, ClockInterface, EncryptorInterface};
use MailDesk\Domain\Configuration\{MailConfiguration, MailConfigurationRepositoryInterface};
use MailDesk\Domain\Exception\ValidationFailed;

final class SaveConfiguration
{
    public function __construct(
        private MailConfigurationRepositoryInterface $configs,
        private EncryptorInterface $encryptor,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
        private string $fingerprintKey,
        private int $testValiditySeconds = 600,
    ) {
    }

    /**
     * @param string|null $testedFingerprint fingerprint of the last successful test (from the session)
     * @param int|null    $testedAt          unix time of that test
     */
    public function execute(ConfigurationInput $in, int $userId, ?string $testedFingerprint = null, ?int $testedAt = null): MailConfiguration
    {
        $stored = $this->configs->getActive();
        $newPassword = (string) $in->password;

        if ($newPassword === '' && $stored === null) {
            $this->failWith($in, ['smtp_password' => 'Password is required.']);
        }

        $encrypted = $newPassword !== '' ? $this->encryptor->encrypt($newPassword) : $stored->encryptedPassword;
        $plain = $newPassword !== '' ? $newPassword : $this->encryptor->decrypt($stored->encryptedPassword);

        $now = $this->clock->now();
        $nowStr = $now->format('Y-m-d H:i:s');

        // Build first to validate; verification is decided afterwards.
        $draft = ConfigurationFactory::build($in, $encrypted, false, null, $stored?->id);
        $fp = ConfigurationFingerprint::of($this->fingerprintKey, $draft->host, $draft->port, $draft->encryption, $draft->username, $plain);

        $verified = false;
        $lastTested = null;
        if ($testedFingerprint !== null && $testedAt !== null
            && hash_equals($fp, $testedFingerprint)
            && ($now->getTimestamp() - $testedAt) <= $this->testValiditySeconds) {
            $verified = true;
            $lastTested = $nowStr;
        } elseif ($stored !== null) {
            $storedFp = ConfigurationFingerprint::of($this->fingerprintKey, $stored->host, $stored->port, $stored->encryption, $stored->username, $this->encryptor->decrypt($stored->encryptedPassword));
            if (hash_equals($storedFp, $fp)) {
                // connection values unchanged: keep previous verification state
                $verified = $stored->verified;
                $lastTested = $stored->lastTestedAt;
            }
        }
        unset($plain);

        $config = new MailConfiguration(
            $stored?->id,
            $draft->senderName,
            $draft->senderEmail,
            $draft->host,
            $draft->port,
            $draft->encryption,
            $draft->username,
            $encrypted,
            $verified,
            $lastTested,
        );

        $this->configs->save($config, $userId, $nowStr);
        $this->audit->log($userId, 'config.updated', ['changed' => $this->changedFields($stored, $config, $newPassword !== ''), 'verified' => $verified]);

        return $config;
    }

    /** @param array<string,string> $errors */
    private function failWith(ConfigurationInput $in, array $errors): never
    {
        try {
            ConfigurationFactory::build($in, '', false, null, null);
        } catch (ValidationFailed $e) {
            $errors += $e->errors();
        }
        throw new ValidationFailed($errors);
    }

    /** @return list<string> field names only, never values */
    private function changedFields(?MailConfiguration $old, MailConfiguration $new, bool $passwordChanged): array
    {
        if ($old === null) {
            return ['sender_name', 'sender_email', 'smtp_host', 'smtp_port', 'encryption', 'smtp_username', 'smtp_password'];
        }
        $changed = [];
        $map = [
            'sender_name' => [$old->senderName, $new->senderName],
            'sender_email' => [(string) $old->senderEmail, (string) $new->senderEmail],
            'smtp_host' => [$old->host, $new->host],
            'smtp_port' => [$old->port, $new->port],
            'encryption' => [$old->encryption, $new->encryption],
            'smtp_username' => [$old->username, $new->username],
        ];
        foreach ($map as $field => [$a, $b]) {
            if ($a !== $b) {
                $changed[] = $field;
            }
        }
        if ($passwordChanged) {
            $changed[] = 'smtp_password';
        }
        return $changed;
    }
}
