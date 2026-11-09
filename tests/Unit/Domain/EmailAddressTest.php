<?php
declare(strict_types=1);

namespace MailDesk\Tests\Unit\Domain;

use MailDesk\Domain\Exception\InvalidEmailAddress;
use MailDesk\Domain\Shared\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    public function test_it_normalises_to_lower_case_and_trims(): void
    {
        self::assertSame('ali@example.com', (string) new EmailAddress("  Ali@Example.COM \n"));
    }

    /** @return array<string,array{string}> */
    public static function invalid(): array
    {
        return [
            'empty' => [''],
            'no at' => ['aliexample.com'],
            'too long' => [str_repeat('a', 250) . '@example.com'],
            'crlf injection' => ["a@example.com\r\nBcc: evil@example.com"],
            'embedded newline' => ["a@exa\nmple.com"],
        ];
    }

    #[DataProvider('invalid')]
    public function test_it_rejects_invalid_addresses(string $value): void
    {
        $this->expectException(InvalidEmailAddress::class);
        new EmailAddress($value);
    }
}
