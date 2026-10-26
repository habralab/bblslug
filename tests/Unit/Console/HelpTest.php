<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use Bblslug\Console\Help;
use Bblslug\Models\ModelRegistry;
use PHPUnit\Framework\TestCase;

class HelpTest extends TestCase
{
    /** @test */
    public function printHelpWithNoExitOptionOutputsUsageAndOptions(): void
    {
        // Capture STDOUT
        ob_start();
        // Call with null to avoid exit()
        Help::printHelp(null);
        $output = ob_get_clean();

        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('--help', $output);
        $this->assertStringContainsString('--list-models', $output);
        $this->assertStringContainsString('Environment:', $output);
        $this->assertStringContainsString('Examples:', $output);
    }

    public function testDefaultAndFullModelListsKeepSnapshotsAndLifecycleSeparate(): void
    {
        ob_start();
        Help::printModelList();
        $default = (string) ob_get_clean();
        ob_start();
        Help::printModelList(true);
        $all = (string) ob_get_clean();

        self::assertStringContainsString('openai:gpt-6-luna', $default);
        self::assertStringContainsString('--list-models=all', $default);
        foreach (
            [
            'openai:gpt-5.5-2026-04-23',
            'google:gemini-2.5-flash',
            'anthropic:claude-sonnet-4.5-20250929',
            'yandex:gpt-oss-120b',
            ] as $extendedKey
        ) {
            self::assertStringNotContainsString($extendedKey, $default);
            self::assertStringContainsString($extendedKey, $all);
        }
        self::assertStringContainsString('shutdown 2026-11-30', $all);
        self::assertStringContainsString('shutdown 2026-10-30', $all);
        self::assertStringNotContainsString('openai:gpt-4-turbo', $all);
    }
}
