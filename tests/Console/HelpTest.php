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

    /** @test */
    public function printModelListGroupsModelsByVendor(): void
    {
        // Prepare a minimal stub registry
        $stub = $this->createMock(ModelRegistry::class);
        $stub->method('getAll')->willReturn([
            'alpha:model-a' => ['vendor' => 'alpha', 'notes' => 'Alpha notes'],
            'beta:model-b'  => ['vendor' => 'beta',  'notes' => 'Beta notes'],
            'alpha:model-c' => ['vendor' => 'alpha', 'notes' => null],
        ]);

        ob_start();
        Help::printModelList($stub);
        $output = ob_get_clean();

        // Should list two vendors
        $this->assertStringContainsString("alpha:", $output);
        $this->assertStringContainsString("beta:", $output);

        // Under alpha, both model keys should appear
        $this->assertStringContainsString('alpha:model-a', $output);
        $this->assertStringContainsString('alpha:model-c', $output);

        // Under beta, its model key should appear
        $this->assertStringContainsString('beta:model-b', $output);

        // Notes should be appended where present
        $this->assertStringContainsString('Alpha notes', $output);
        $this->assertStringContainsString('Beta notes', $output);
    }
}
