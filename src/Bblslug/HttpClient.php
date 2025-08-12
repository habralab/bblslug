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
     */
    public static function request(
        string $method,
        string $url,
        string $body = '',
        bool $dryRun = false,
        array $headers = [],
        array $maskPatterns = [],
        ?string $proxy = null,
        bool $verbose = false
    ): array {
        // Prepare masked copies for logging
        $logHeaders = $headers;
        $logBody    = $body;

        if (!empty($maskPatterns)) {
            foreach ($maskPatterns as $pat) {
                foreach ($logHeaders as &$h) {
                    $h = str_replace($pat, '***', $h);
                }
                unset($h);
                $logBody = str_replace($pat, '***', $logBody);
            }
        }

        // Build request debug log
        $debugRequest = '';
        if ($verbose || $dryRun) {
            $title = $dryRun
                   ? 'Dry-run: request (not sent)'
                   : 'Verbose: request preview';
            $debugRequest .= "\n\033[1m{$title}\033[0m\n";
            $debugRequest .= "{$method} {$url}\n\nHeaders:\n";
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

        if (\curl_errno($ch)) {
            $err = \curl_error($ch);
            \curl_close($ch);
            throw new \RuntimeException("Network error: {$err}");
        }
        $status = (int)\curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);

        // Build response debug log
        $debugResponse = '';
        if ($verbose) {
            $debugResponse .= "\n\033[1mResponse ({$status})\033[0m\nHeaders:\n";
            foreach ($responseHeaders as $name => $vals) {
                foreach ($vals as $v) {
                    $val = $v;
                    foreach ($maskPatterns as $pat) {
                        $val = str_replace($pat, '***', $val);
                    }
                    $debugResponse .= "  {$name}: {$val}\n";
                }
            }
            $logRespBody = (string) $respBody;
            foreach ($maskPatterns as $pat) {
                $logRespBody = str_replace($pat, '***', $logRespBody);
            }
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
}
