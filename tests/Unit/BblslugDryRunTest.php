<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;
use Bblslug\Bblslug;
use Bblslug\Models\ModelRegistry;
use Bblslug\Models\Drivers\DeepLDriver;

/**
 * Atomic dry-run tests for the main facade Bblslug::translate().
 * No real HTTP calls are performed; we focus on plumbing:
 * - filters pipeline (apply → restore)
 * - length/validation guards (for json)
 * - debug and stats fields shape
 */
final class BblslugDryRunTest extends TestCase
{
    /**
     * Find any modelKey in the registry that uses the given driver class.
     * If none is found, mark the test as skipped to avoid hardcoding keys.
     */
    private function findModelKeyForDriver(string $driverClass): ?string
    {
        $reg = new ModelRegistry();
        foreach ($reg->getAll() as $key => $_cfg) {
            $drv = $reg->getDriver($key);
            if ($drv instanceof $driverClass) {
                return $key;
            }
        }
        return null;
    }

    /** @test */
    public function dryRunTextWithFiltersRestoresOriginalAndReportsStats(): void
    {
        $modelKey = $this->findModelKeyForDriver(DeepLDriver::class);
        if ($modelKey === null) {
            $this->markTestSkipped('No DeepL model found in registry.');
        }

        $input = 'Read <a href="https://example.com/page?id=1">link</a> or https://another.tld';
        $apiKey = 'SECRET_KEY';

        $out = Bblslug::translate(
            apiKey:    $apiKey,
            format:    'text',
            modelKey:  $modelKey,
            text:      $input,
            context:   null,
            dryRun:    true,                 // important: no HTTP
            filters:   ['url', 'html_a'],    // mask URLs then <a> blocks
            onFeedback: null,
            promptKey: 'translator',
            proxy:     null,
            sourceLang: null,
            targetLang: null,
            validate:  true,
            variables: [],
            verbose:   true
        );

        // Shape and basic fields
        $this->assertSame($input, $out['original']);
        $this->assertSame(0, $out['httpStatus']);                 // dry-run uses status 0
        $this->assertIsString($out['debugRequest']);
        $this->assertIsString($out['debugResponse']);             // empty in dry-run
        $this->assertIsString($out['rawResponseBody']);           // "[dry-run]" from HttpClient

        // Filters were applied (prepared has placeholders), then restored (result == original)
        $this->assertNotSame($input, $out['prepared']);
        $this->assertMatchesRegularExpression('/@@\d+@@/', $out['prepared']);
        $this->assertSame($input, $out['result']);

        // Lengths are consistent
        $this->assertIsArray($out['lengths']);
        $this->assertSame(mb_strlen($input), $out['lengths']['original']);
        $this->assertSame(mb_strlen($out['prepared']), $out['lengths']['prepared']);
        $this->assertSame(mb_strlen($out['result']), $out['lengths']['translated']);

        // Filter stats should report counts per filter (2 URLs, 1 <a> block)
        $this->assertIsArray($out['filterStats']);
        $this->assertSame('url', $out['filterStats'][0]['filter']);
        $this->assertSame(2, $out['filterStats'][0]['count']);
        $this->assertSame('html_a', $out['filterStats'][1]['filter']);
        $this->assertSame(1, $out['filterStats'][1]['count']);

        // Consumed usage is empty in dry-run
        $this->assertSame([], $out['consumed']);
    }

    /** @test */
    public function dryRunJsonValidatesSchemaBeforeAndAfter(): void
    {
        $modelKey = $this->findModelKeyForDriver(DeepLDriver::class);
        if ($modelKey === null) {
            $this->markTestSkipped('No DeepL model found in registry.');
        }

        $json = json_encode([
            'article' => [
                'name' => 'Some Name',
                'description' => 'Some Description',
                'tags' => ['one', 'two'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $out = Bblslug::translate(
            apiKey:    'SECRET',
            format:    'json',
            modelKey:  $modelKey,
            text:      $json,
            dryRun:    true,    // skip HTTP, keep validation path
            filters:   [],      // no masking to keep JSON intact
            verbose:   true
        );

        // Should round-trip untouched in dry-run (schema must match)
        $this->assertSame($json, $out['original']);
        $this->assertSame($json, $out['prepared']);
        $this->assertSame($json, $out['result']);

        // Debug logs should include validation markers when verbose=true
        $this->assertStringContainsString('[JSON schema captured]', $out['debugRequest']);
        $this->assertStringContainsString('[JSON schema validated]', $out['debugResponse']);
    }
}
