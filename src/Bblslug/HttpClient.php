<?php

declare(strict_types=1);

namespace Bblslug;

/**
 * Simple HTTP client wrapper around cURL.
 */
class HttpClient
{
    /**
     * Execute an HTTP request and return detailed response information.
     *
     * @param non-empty-string $method     HTTP method (e.g. 'GET', 'POST', 'PUT').
     * @param string        $url           Full URL to request.
     *
     * @param string        $body          Request body (optional).
     * @param bool          $dryRun        If true, skip real request and return placeholder.
     * @param array<int,string> $headers       Array of headers in "Name: value" format.
     * @param array<int,string> $maskPatterns  Substrings to mask in debug logs.
     * @param string|null   $proxy         Optional proxy URI (http, socks5, etc.).
     * @param bool          $verbose       If true, include request/response debug logs.
     * @param int|null      $timeoutSeconds Total request timeout; overrides BBLSLUG_HTTP_TIMEOUT (default 120).
     * @param int|null      $connectTimeoutSeconds Connection timeout in seconds (default 10).
     *                                            Overrides BBLSLUG_HTTP_CONNECT_TIMEOUT.
     * @param int|null      $timeoutMilliseconds Optional total request timeout cap in milliseconds.
     *
     * @return array{
     *     body: string,
     *     debugRequest: string,
     *     debugResponse: string,
     *     headers: array<string, array<int,string>>,
     *     status: int
     * }
     *
     * @throws \RuntimeException On network or cURL errors.
     * @throws \InvalidArgumentException On invalid timeout settings.
     */
    public static function request(
        string $method,
        string $url,
        string $body = '',
        bool $dryRun = false,
        array $headers = [],
        array $maskPatterns = [],
        ?string $proxy = null,
        bool $verbose = false,
        ?int $timeoutSeconds = null,
        ?int $connectTimeoutSeconds = null,
        ?int $timeoutMilliseconds = null
    ): array {
        $timeoutSeconds = self::resolveTimeout($timeoutSeconds, 'BBLSLUG_HTTP_TIMEOUT', 120);
        $connectTimeoutSeconds = self::resolveTimeout($connectTimeoutSeconds, 'BBLSLUG_HTTP_CONNECT_TIMEOUT', 10);
        if ($timeoutMilliseconds !== null && $timeoutMilliseconds <= 0) {
            throw new \InvalidArgumentException('timeoutMilliseconds must be a positive integer in milliseconds.');
        }

        $masker = new SecretMasker($maskPatterns);
        $logUrl = $masker->redact($url);
        $logHeaders = array_map($masker->redact(...), $headers);
        $logBody = $masker->redact($body);

        // Build request debug log
        $debugRequest = '';
        if ($verbose || $dryRun) {
            $title = $dryRun
                   ? 'Dry-run: request (not sent)'
                   : 'Verbose: request preview';
            $debugRequest .= "\n\033[1m{$title}\033[0m\n";
            $debugRequest .= "{$method} {$logUrl}\n\nHeaders:\n";
            foreach ($logHeaders as $h) {
                $debugRequest .= "  {$h}\n";
            }
            if ($logBody !== '') {
                $debugRequest .= "\nBody:\n{$logBody}\n\n";
            }
        }

        // If dry-run, return early with debug info
        if ($dryRun) {
            return [
                'status'        => 0,
                'headers'       => [],
                'body'          => '[dry-run]',
                'debugRequest'  => $debugRequest,
                'debugResponse' => '',
            ];
        }

        // Perform real cURL request
        $ch = \curl_init($url);
        \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        \curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeoutSeconds);
        if ($timeoutMilliseconds !== null) {
            $effectiveTimeoutMilliseconds = self::capMilliseconds($timeoutSeconds, $timeoutMilliseconds);
            $effectiveConnectTimeoutMilliseconds = self::capMilliseconds(
                $connectTimeoutSeconds,
                $effectiveTimeoutMilliseconds
            );
            \curl_setopt($ch, CURLOPT_TIMEOUT_MS, $effectiveTimeoutMilliseconds);
            \curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $effectiveConnectTimeoutMilliseconds);
        }
        if (!empty($headers)) {
            \curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($method !== 'GET') {
            \curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if ($proxy !== null && $proxy !== '') {
            /** @var non-empty-string $proxyVal */
            $proxyVal = $proxy;
            \curl_setopt($ch, CURLOPT_PROXY, $proxyVal);
        }

        // Capture response headers
        /** @var array<string, array<int,string>> $responseHeaders */
        $responseHeaders = [];
        \curl_setopt(
            $ch,
            CURLOPT_HEADERFUNCTION,
            /**
             * @param resource $curl
             */
            function ($curl, string $line) use (&$responseHeaders): int {


                if (strpos($line, ':') !== false) {
                    [$name, $value] = explode(':', trim($line), 2);
                    $responseHeaders[$name][] = trim($value);
                }
                return strlen($line);
            }
        );

        $respBody = \curl_exec($ch);
        if ($respBody === false) {
            $respBody = '';
        }

        $errorCode = \curl_errno($ch);
        if ($errorCode !== 0) {
            $err = $masker->redact(\curl_error($ch));
            throw new \RuntimeException("Network error: {$err}", $errorCode);
        }
        $status = (int)\curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Build response debug log
        $debugResponse = '';
        if ($verbose) {
            $debugResponse .= "\n\033[1mResponse ({$status})\033[0m\nHeaders:\n";
            foreach ($responseHeaders as $name => $vals) {
                foreach ($vals as $v) {
                    $val = $masker->redact($v);
                    $logName = $masker->redact($name);
                    $debugResponse .= "  {$logName}: {$val}\n";
                }
            }
            $logRespBody = $masker->redact((string) $respBody);
            $debugResponse .= "\nBody:\n{$logRespBody}\n\n";
        }

        return [
            'status'        => $status,
            'headers'       => $responseHeaders,
            'body'          => (string)$respBody,
            'debugRequest'  => $debugRequest,
            'debugResponse' => $debugResponse,
        ];
    }

    private static function resolveTimeout(?int $explicit, string $environmentName, int $default): int
    {
        if ($explicit !== null) {
            if ($explicit <= 0) {
                throw new \InvalidArgumentException(
                    "{$environmentName} override must be a positive integer in seconds."
                );
            }
            return $explicit;
        }

        $value = getenv($environmentName);
        if ($value === false || $value === '') {
            return $default;
        }

        $seconds = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($seconds === false) {
            throw new \InvalidArgumentException("{$environmentName} must be a positive integer in seconds.");
        }
        return $seconds;
    }

    private static function capMilliseconds(int $seconds, int $cap): int
    {
        // Avoid overflowing when converting a valid, very large second timeout.
        $maxSeconds = intdiv(PHP_INT_MAX, 1000);
        if ($seconds > $maxSeconds) {
            return $cap;
        }

        return min($seconds * 1000, $cap);
    }
}
