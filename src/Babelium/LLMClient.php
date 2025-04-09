<?php

namespace Babelium;

class LLMClient
{
    /**
     * Send a request to the specified model's endpoint using provided data and API key.
     *
     * @param array $model  Model configuration from registry.
     * @param array $payload Payload fields required for request body.
     * @param string $apiKey API key from environment or CLI.
     * @return string Raw response from the API.
     */
    public static function send(array $model, array $payload, string $apiKey, bool $isDryRun = false, bool $isVerbose = false): string
    {
        $endpoint = $model['endpoint'];
        $headers = $model['requirements']['headers'] ?? [];
        $auth = $model['requirements']['auth'] ?? null;

        // Handle auth
        if ($auth) {
            $type = $auth['type'] ?? 'form';
            $key = $auth['key_name'] ?? 'auth_key';
            $prefix = $auth['prefix'] ? $auth['prefix'] . ' ' : '';

            if ($type === 'header') {
                $headers[] = $key . ': ' . $prefix . $apiKey;
            } elseif ($type === 'form') {
                $payload[$key] = $apiKey;
            } elseif ($type === 'query') {
                $endpoint .= (str_contains($endpoint, '?') ? '&' : '?') . http_build_query([$key => $apiKey]);
            }
        }

        $bodyType = $model['requirements']['body_type'] ?? 'form';
        $body = $bodyType === 'json'
            ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : http_build_query($payload);

        // Print debug info if verbose or dry-run
        if ($isVerbose || $isDryRun) {
            $title = $isDryRun ? 'Dry-run: request data (not sent)' : 'Verbose: request preview';
            echo "\n\033[1m{$title}\033[0m\n";
            echo "Endpoint:\n  $endpoint\n";
            echo "Headers:\n";
            foreach ($headers as $header) {
                echo "  $header\n";
            }
            echo "Body:\n";
            echo ($bodyType === 'json' ? $body : urldecode($body)) . "\n\n";
        }

        if ($isDryRun) {
            return "[dry-run]"; // dummy value to keep flow consistent
        }

        // Perform request
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}

