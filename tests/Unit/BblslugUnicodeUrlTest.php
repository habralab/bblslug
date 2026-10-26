<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugUnicodeUrlTest extends TestCase
{
    public function testProviderTranslationRestoresOriginalUrlEscapesAtEveryJsonLayer(): void
    {
        $url = 'https:\u002f\u002fexample.test\u002fpath\u003fq\u003d\u00e9';
        for ($depth = 0; $depth <= 2; $depth++) {
            $source = '{"label":"Hello","link":"' . $url . '","id":9223372036854775809}';
            $expected = str_replace('Hello', 'Hallo', $source);
            $translated = '{"label":"Hallo","link":"@@0@@","id":9223372036854775809}';
            for ($i = 0; $i < $depth; $i++) {
                $source = json_encode(['payload' => $source], JSON_THROW_ON_ERROR);
                $expected = json_encode(['payload' => $expected], JSON_THROW_ON_ERROR);
                $translated = json_encode(['payload' => $translated], JSON_THROW_ON_ERROR);
            }
            $process = proc_open(
                [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/json_output.php'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            self::assertIsResource($process);
            fwrite($pipes[0], json_encode([
                'input' => $source, 'translated' => $translated, 'filters' => ['url'],
                'validateEmbeddedJson' => true,
            ], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), (string) $stderr);
            $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('success', $result['type'], $result['message'] ?? '');
            self::assertSame($expected, $result['result']);
        }
    }
}
