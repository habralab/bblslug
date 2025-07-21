<?php

namespace Bblslug\Models;

/**
 * Driver interface for translation models/vendors.
 */
interface ModelDriverInterface
{
    /**
     * Build the HTTP request parameters for this model.
     *
     * @param array<string,mixed> $config  Model config from registry (endpoint, requirements, defaults, etc.)
     * @param string              $text    Text or HTML after placeholder filters are applied
     * @param array<string,mixed> $options Request options (all optional):
     *     - dryRun  (bool)   Skip actual API call and return a placeholder response
     *     - format  (string) 'text' or 'html'
     *     - verbose (bool)   Include debug information in the output
     *
     * @return array{
     *     body:    string,     // URL-encoded form or JSON payload
     *     headers: string[],   // HTTP headers to send
     *     url:     string      // Full endpoint URL
     * }
     */
    public function buildRequest(array $config, string $text, array $options): array;

    /**
     * Parse the raw API response into translated text.
     *
     * @param array<string,mixed> $config       Model config from registry
     * @param string              $responseBody Raw response body from the API
     *
     * @return string Translated text
     *
     * @throws \RuntimeException If the response is malformed or indicates an error
     */
    public function parseResponse(array $config, string $responseBody): string;
}
