<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;
use Bblslug\HttpClient;

/**
 * Local integration-style unit tests for HttpClient.
 *
 * We spin up a tiny local HTTP server via PHP's built-in server,
 * so we can exercise real cURL calls (including header parsing)
 * without relying on the public internet or proxies.
 *
 * No changes in product code are required.
 */
final class HttpClientTest extends TestCase
{
    /** @var resource|false */
    private static $serverProc = false;

    private static int $port;
    private static string $host = '127.0.0.1';

    /** @var array<string,string|false> */
    private array $originalTimeoutEnvironment = [];

    protected function setUp(): void
    {
        foreach (['BBLSLUG_HTTP_TIMEOUT', 'BBLSLUG_HTTP_CONNECT_TIMEOUT'] as $name) {
            $this->originalTimeoutEnvironment[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalTimeoutEnvironment as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
    }

    /**
     * Start the local test server once for the whole class.
     */
    public static function setUpBeforeClass(): void
    {
        // Pick a free ephemeral port by binding to port 0 first.
        $errno = 0;
        $errstr = '';
        $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!is_resource($sock)) {
            self::markTestSkipped('Cannot allocate a local TCP port for the test server.');
        }
        // `stream_socket_get_name` is portable and returns "ip:port".
        $local = @stream_socket_get_name($sock, false);
        if ($local === false || !preg_match('~:(\d+)$~', $local, $m)) {
            fclose($sock);
            self::markTestSkipped('Cannot determine allocated port.');
        }
        self::$port = (int) $m[1];
        fclose($sock);

        // Paths for the router and docroot.
        $docroot = realpath(__DIR__ . '/../Fixtures/httpserver');
        $router  = $docroot . '/router.php';

        if ($docroot === false || !is_dir($docroot) || !is_file($router)) {
            self::markTestSkipped('Test HTTP server router not found.');
        }

        // Start PHP built-in server with our router.
        $cmd = sprintf(
            '%s -S %s:%d %s',
            escapeshellarg(PHP_BINARY),
            self::$host,
            self::$port,
            escapeshellarg($router)
        );

        // Use proc_open so we can terminate it later.
        // We inherit stdio; no output capture needed for tests.
        self::$serverProc = proc_open(
            $cmd,
            [
                0 => ['file', 'php://stdin', 'r'],
                1 => ['file', 'php://stdout', 'w'],
                2 => ['file', 'php://stderr', 'w'],
            ],
            $pipes,
            $docroot,
            [
                // Ensure single worker to avoid concurrency surprises.
                'PHP_CLI_SERVER_WORKERS' => '1',
            ]
        );

        if (!is_resource(self::$serverProc)) {
            self::$serverProc = false;
            self::markTestSkipped('Failed to start local PHP server.');
        }

        // Give the server a brief moment to start.
        usleep(200_000);
    }

    /**
     * Stop the local server.
     */
    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProc)) {
            // Politely ask to terminate; then ensure process is closed.
            proc_terminate(self::$serverProc);
            proc_close(self::$serverProc);
            self::$serverProc = false;
        }
    }

    private function url(string $path): string
    {
        return sprintf('http://%s:%d%s', self::$host, self::$port, $path);
    }

    /** @test */
    public function dryRunBuildsRequestLogAndMasksSecrets(): void
    {
        $secret = 'MYSECRET';
        $resp = HttpClient::request(
            method: 'POST',
            url: $this->url('/echo'),
            body: "token={$secret}",
            dryRun: true,
            headers: ["Authorization: Bearer {$secret}"],
            maskPatterns: [$secret],
            proxy: null,
            verbose: true
        );

        // Dry-run never hits the network.
        $this->assertSame(0, $resp['status']);
        $this->assertSame('[dry-run]', $resp['body']);

        // Request preview must include title and method+URL.
        $this->assertStringContainsString('Dry-run: request (not sent)', $resp['debugRequest']);
        $this->assertStringContainsString('POST ' . $this->url('/echo'), $resp['debugRequest']);

        // Secret must be masked in request log.
        $this->assertStringNotContainsString($secret, $resp['debugRequest']);
        $this->assertStringContainsString('***', $resp['debugRequest']);

        // No response log is emitted on dry-run.
        $this->assertSame('', $resp['debugResponse']);
    }

    /** @test */
    public function verboseSuccessParsesHeadersAndMasksResponse(): void
    {
        $secret = 'TOPSECRET';
        $resp = HttpClient::request(
            method: 'GET',
            url: $this->url('/mask?secret=' . rawurlencode($secret)),
            body: '',
            dryRun: false,
            headers: ["X-Secret: {$secret}"],
            maskPatterns: [$secret],
            proxy: null,
            verbose: true
        );

        // Real HTTP status is propagated.
        $this->assertSame(200, $resp['status']);

        // Response headers are aggregated by name and exposed as arrays (case-insensitive check).
        $hdrs = array_change_key_case($resp['headers'], CASE_LOWER);
        $this->assertArrayHasKey('x-token', $hdrs);
        $this->assertIsArray($hdrs['x-token']);

        // Response debug must include status and a body section.
        $this->assertStringContainsString('Response (200)', $resp['debugResponse']);
        $this->assertStringContainsString("\nBody:\n", $resp['debugResponse']);

        // Secret must be masked in response debug (headers and body).
        $this->assertStringNotContainsString($secret, $resp['debugResponse']);
        $this->assertStringContainsString('***', $resp['debugResponse']);
    }

    /** @test */
    public function postSendsBodyAndServerSeesRealMethod(): void
    {
        $payload = 'hello=world';
        $resp = HttpClient::request(
            method: 'POST',
            url: $this->url('/echo'),
            body: $payload,
            dryRun: false,
            headers: ['Content-Type: application/x-www-form-urlencoded'],
            maskPatterns: [],
            proxy: null,
            verbose: false
        );

        $this->assertSame(200, $resp['status']);

        $data = json_decode($resp['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('POST', $data['method']);
        $this->assertSame($payload, $data['body']);

        // No debug logs in non-verbose mode.
        $this->assertSame('', $resp['debugRequest']);
        $this->assertSame('', $resp['debugResponse']);
    }

    /** @test */
    public function networkErrorThrowsRuntimeexception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Network error:/');
        $this->expectExceptionCode(CURLE_COULDNT_CONNECT);

        // Port 9 (discard) is expected to be closed locally in test envs.
        HttpClient::request(
            method: 'GET',
            url: 'http://127.0.0.1:9/',
            body: '',
            dryRun: false,
            headers: [],
            maskPatterns: [],
            proxy: null,
            verbose: false
        );
    }

    /** @test */
    public function customStatusAndHeaderMultivalueAreHandled(): void
    {
        $resp = HttpClient::request(
            method: 'GET',
            url: $this->url('/status/201'),
            body: '',
            dryRun: false,
            headers: [],
            maskPatterns: [],
            proxy: null,
            verbose: true
        );

        $this->assertSame(201, $resp['status']);
        $hdrs = array_change_key_case($resp['headers'], CASE_LOWER);
        $this->assertArrayHasKey('content-type', $hdrs);
        $this->assertNotEmpty($hdrs['content-type']);
        $firstCt = (string)($hdrs['content-type'][0] ?? '');
        $this->assertNotSame('', $firstCt);
        $mainType = strtolower(trim(explode(';', $firstCt, 2)[0]));
        $this->assertSame('text/plain', $mainType);
        $this->assertStringContainsString('Response (201)', $resp['debugResponse']);
    }

    /** @test */
    public function explicitTimeoutsOverrideInvalidEnvironmentValues(): void
    {
        putenv('BBLSLUG_HTTP_TIMEOUT=invalid');
        putenv('BBLSLUG_HTTP_CONNECT_TIMEOUT=0.5');

        $resp = HttpClient::request(
            method: 'GET',
            url: $this->url('/echo'),
            dryRun: true,
            timeoutSeconds: 2,
            connectTimeoutSeconds: 1
        );

        $this->assertSame(0, $resp['status']);
        $this->assertSame('[dry-run]', $resp['body']);
    }

    /** @test */
    public function nonpositiveExplicitTimeoutsAreRejected(): void
    {
        $timeoutCases = [
            ['timeoutSeconds' => 0],
            ['timeoutSeconds' => -1],
            ['connectTimeoutSeconds' => 0],
            ['connectTimeoutSeconds' => -1],
        ];

        foreach ($timeoutCases as $arguments) {
            try {
                $requestArguments = [
                    'method' => 'GET',
                    'url' => $this->url('/echo'),
                    'dryRun' => true,
                ] + $arguments;
                HttpClient::request(...$requestArguments);
                $this->fail('A nonpositive explicit timeout should be rejected.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** @test */
    public function invalidTimeoutEnvironmentValuesNameTheSetting(): void
    {
        $environmentCases = [
            'BBLSLUG_HTTP_TIMEOUT' => ['0', '-1', 'invalid', '1.5'],
            'BBLSLUG_HTTP_CONNECT_TIMEOUT' => ['0', '-1', 'invalid', '1.5'],
        ];

        foreach ($environmentCases as $name => $values) {
            foreach ($values as $value) {
                putenv($name . '=' . $value);

                try {
                    HttpClient::request(
                        method: 'GET',
                        url: $this->url('/echo'),
                        dryRun: true
                    );
                    $this->fail("Invalid {$name} should be rejected.");
                } catch (\InvalidArgumentException $exception) {
                    $this->assertStringContainsString($name, $exception->getMessage());
                }
            }
            putenv($name);
        }
    }

    /** @test */
    public function timeoutNetworkErrorCarriesCurlTimeoutCode(): void
    {
        try {
            HttpClient::request(
                method: 'GET',
                url: $this->url('/slow'),
                timeoutSeconds: 1,
                connectTimeoutSeconds: 1
            );
            $this->fail('The slow response should exceed the request timeout.');
        } catch (\RuntimeException $exception) {
            $this->assertStringStartsWith('Network error:', $exception->getMessage());
            $this->assertSame(CURLE_OPERATION_TIMEDOUT, $exception->getCode());
        }
    }
}
