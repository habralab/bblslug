<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugErrorRedactionTest extends TestCase
{
    private const API_KEY = 'demo +/key=value';

    /** @return array<string, array{model:string,status:int,body:string,reason:string,verbose:bool}> */
    public static function providerErrors(): array
    {
        $rawKey = self::API_KEY;
        $rawUrlEncoded = rawurlencode($rawKey);
        $urlEncoded = urlencode($rawKey);

        return [
            'google error with nested encoded key' => [
                'model' => 'google:gemini-2.5-flash',
                'status' => 401,
                'body' => json_encode(
                    ['message' => json_encode(['key' => $rawKey], JSON_THROW_ON_ERROR)],
                    JSON_THROW_ON_ERROR
                ),
                'reason' => 'HTTP 401 error',
                'verbose' => true,
            ],
            'google http error' => [
                'model' => 'google:gemini-2.5-flash',
                'status' => 401,
                'body' => "Invalid API key {$rawKey} {$rawUrlEncoded} {$urlEncoded}",
                'reason' => 'HTTP 401 error',
                'verbose' => false,
            ],
            'google http error verbose' => [
                'model' => 'google:gemini-2.5-flash',
                'status' => 401,
                'body' => "Invalid API key {$rawKey} {$rawUrlEncoded} {$urlEncoded}",
                'reason' => 'HTTP 401 error',
                'verbose' => true,
            ],
            'anthropic credit error' => [
                'model' => 'anthropic:claude-haiku-5.5',
                'status' => 400,
                'body' => json_encode([
                    'type' => 'error',
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => "credit balance too low ({$rawKey} {$rawUrlEncoded} {$urlEncoded})",
                    ],
                ], JSON_UNESCAPED_SLASHES),
                'reason' => 'credit balance too low',
                'verbose' => false,
            ],
            'anthropic credit error verbose' => [
                'model' => 'anthropic:claude-haiku-5.5',
                'status' => 400,
                'body' => json_encode([
                    'type' => 'error',
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => "credit balance too low ({$rawKey} {$rawUrlEncoded} {$urlEncoded})",
                    ],
                ], JSON_UNESCAPED_SLASHES),
                'reason' => 'credit balance too low',
                'verbose' => true,
            ],
            'google malformed response' => [
                'model' => 'google:gemini-2.5-flash',
                'status' => 200,
                'body' => json_encode([
                    'echo' => "{$rawKey} {$rawUrlEncoded} {$urlEncoded}",
                ]),
                'reason' => 'Gemini response has no candidates',
                'verbose' => false,
            ],
            'google malformed response verbose' => [
                'model' => 'google:gemini-2.5-flash',
                'status' => 200,
                'body' => json_encode([
                    'echo' => "{$rawKey} {$rawUrlEncoded} {$urlEncoded}",
                ]),
                'reason' => 'Gemini response has no candidates',
                'verbose' => true,
            ],
        ];
    }

    /** @dataProvider providerErrors */
    public function testProviderFailureDoesNotEchoApiKey(
        string $model,
        int $status,
        string $body,
        string $reason,
        bool $verbose
    ): void {
        $scenario = [
            'model' => $model,
            'status' => $status,
            'body' => $body,
            'reason' => $reason,
            'verbose' => $verbose,
        ];
        $result = $this->runFixture($scenario);
        $rawKey = self::API_KEY;

        $this->assertSame('RuntimeException', $result['type']);
        $this->assertSame(1, $result['requestCount']);
        $this->assertStringContainsString($scenario['reason'], $result['message']);
        $this->assertStringNotContainsString($rawKey, $result['message']);
        $this->assertStringNotContainsString(rawurlencode($rawKey), $result['message']);
        $jsonOnce = substr(json_encode($rawKey, JSON_THROW_ON_ERROR), 1, -1);
        $jsonTwice = substr(json_encode($jsonOnce, JSON_THROW_ON_ERROR), 1, -1);
        $this->assertStringNotContainsString($jsonTwice, $result['message']);
        $this->assertStringNotContainsString(urlencode($rawKey), $result['message']);
        $this->assertStringNotContainsString(
            trim((string)json_encode($rawKey), '"'),
            $result['message']
        );
    }

    /**
     * @param array{model:string,status:int,body:string,reason:string,verbose:bool} $scenario
     * @return array{type:string,message:string,requestCount:int}
     */
    private function runFixture(array $scenario): array
    {
        $fixture = dirname(__DIR__) . '/Fixtures/translation/provider_error.php';
        $command = [
            PHP_BINARY,
            $fixture,
            '--model=' . $scenario['model'],
            '--status=' . $scenario['status'],
            '--body=' . base64_encode($scenario['body']),
            '--api-key=' . self::API_KEY,
            '--verbose=' . ($scenario['verbose'] ? '1' : '0'),
        ];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, dirname(__DIR__, 2), []);

        $this->assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $stderr);
        $decoded = json_decode($stdout, true);
        $this->assertIsArray($decoded, $stdout);

        /** @var array{type:string,message:string,requestCount:int} $decoded */
        return $decoded;
    }
}
