<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use Bblslug\SecretMasker;
use PHPUnit\Framework\TestCase;

final class SecretMaskerTest extends TestCase
{
    public function testMixedCaseHexEscapesAreMaskedWithoutFoldingLiteralKeyCharacters(): void
    {
        $masker = new SecretMasker(['Key/+é"']);
        self::assertSame('token=***', $masker->redact('token=Key%2f%2B%c3%A9%22'));
        self::assertSame('token=***', $masker->redact('token=Key\/+\u00E9\"'));
        self::assertSame('token=key%2f%2B%c3%A9%22', $masker->redact('token=key%2f%2B%c3%A9%22'));
    }

    public function testNestedJsonRepresentationsAndOverlappingKeysAreMaskedSimultaneously(): void
    {
        $secret = 'prefix/"\\ café';
        $once = substr(json_encode($secret, JSON_THROW_ON_ERROR), 1, -1);
        $twice = substr(json_encode($once, JSON_THROW_ON_ERROR), 1, -1);
        $masker = new SecretMasker(['prefix', $secret]);
        self::assertSame('*** | *** | ***', $masker->redact($secret . ' | ' . $once . ' | ' . $twice));
    }

    public function testEmptyAndNumericSecretsAndInvalidUtf8DoNotBreakMasking(): void
    {
        self::assertSame('ordinary text', (new SecretMasker(['']))->redact('ordinary text'));
        self::assertSame('*** ***', (new SecretMasker(['123', '0']))->redact('123 0'));
        self::assertSame('*** ***', (new SecretMasker(["\xffkey"]))->redact("\xffkey %ffkey"));
    }
}
