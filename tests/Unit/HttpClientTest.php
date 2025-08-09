<?php

declare(strict_types=1);

// Stub cURL functions in the Bblslug namespace so HttpClient::request()
// will use these instead of the real extensions.
namespace Bblslug;

function curl_init($url)
{
    // Return a dummy resource handle
    return fopen('php://memory', 'r+');
}

function curl_errno($ch)
{
    return 0;
}

function curl_error($ch)
{
    return '';
}

function curl_exec($ch)
{
    // Simulate a successful body
    return 'response body';
}

function curl_close($ch)
{
    fclose($ch);
}

function curl_getinfo($ch, $opt)
{
    // Only care about HTTP_CODE
    if ($opt === \CURLINFO_HTTP_CODE) {
        return 201;
    }
    return null;
}

function curl_setopt($ch, $option, $value)
{
    // No-op for stubs
    return true;
}

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;
use Bblslug\HttpClient;

class HttpClientTest extends TestCase
{
    /** @test */
    public function dryRunReturnsPlaceholderAndLogs(): void
    {
        $secret = 'MYSECRET';
        $resp = HttpClient::request(
            method: 'POST',
            url: 'https://example.com/api',
            body: "token={$secret}",
            dryRun: true,
            headers: ["Authorization: Bearer {$secret}"],
            maskPatterns: [$secret],
            proxy: null,
            verbose: true
        );

        $this->assertSame(0, $resp['status']);
        $this->assertSame('[dry-run]', $resp['body']);

        // Should include dry-run title
        $this->assertStringContainsString(
            'Dry-run: request (not sent)',
            $resp['debugRequest']
        );

        // The secret must be masked
        $this->assertStringNotContainsString($secret, $resp['debugRequest']);
        $this->assertStringContainsString('***', $resp['debugRequest']);

        // No response debug on dry-run
        $this->assertSame('', $resp['debugResponse']);
    }

    /** @test */
    public function requestWithVerboseLogsAndMasking(): void
    {
        $secret = 'TOPSECRET';
        $resp = HttpClient::request(
            method: 'GET',
            url: 'https://example.com/data',
            body: '',
            dryRun: false,
            headers: ["Authorization: Bearer {$secret}"],
            maskPatterns: [$secret],
            proxy: 'http://proxy.example:8080',
            verbose: true
        );

        // From our stub: HTTP 201 and body 'response body'
        $this->assertSame(201, $resp['status']);
        $this->assertSame('response body', $resp['body']);

        // Request log must include method and URL, masked secret
        $this->assertStringContainsString(
            'GET https://example.com/data',
            $resp['debugRequest']
        );
        $this->assertStringNotContainsString($secret, $resp['debugRequest']);
        $this->assertStringContainsString('***', $resp['debugRequest']);

        // Response log must report status and include body
        $this->assertStringContainsString(
            'Response (201)',
            $resp['debugResponse']
        );
        $this->assertStringContainsString(
            "Body:\nresponse body",
            $resp['debugResponse']
        );
        // Secret must also be masked in response log
        $this->assertStringNotContainsString($secret, $resp['debugResponse']);
    }

    /** @test */
    public function nonVerboseAndNonDryRunProducesNoDebugLogs(): void
    {
        $resp = HttpClient::request(
            method: 'GET',
            url: 'https://example.com/quiet',
            body: '',
            dryRun: false,
            headers: [],
            maskPatterns: [],
            proxy: null,
            verbose: false
        );

        $this->assertSame('', $resp['debugRequest']);
        $this->assertSame('', $resp['debugResponse']);
    }
}
