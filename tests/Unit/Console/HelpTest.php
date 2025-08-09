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
}
