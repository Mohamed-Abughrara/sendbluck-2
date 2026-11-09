<?php
declare(strict_types=1);

namespace MailDesk\Domain\Configuration;

interface MailConfigurationRepositoryInterface
{
    public function getActive(): ?MailConfiguration;

    public function save(MailConfiguration $config, int $userId, string $now): void;

    /** Update verification state of the stored config only. */
    public function markTested(bool $verified, string $now): void;
}
