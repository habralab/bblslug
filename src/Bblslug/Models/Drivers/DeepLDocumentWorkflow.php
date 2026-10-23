<?php

declare(strict_types=1);

namespace Bblslug\Models\Drivers;

use Bblslug\HttpWorkflowInterface;

/**
 * Execute DeepL's asynchronous document upload, polling, and download flow.
 *
 * @phpstan-import-type HttpRequest from HttpWorkflowInterface
 * @phpstan-import-type HttpResponse from HttpWorkflowInterface
 */
class DeepLDocumentWorkflow implements HttpWorkflowInterface
{
    private string $documentUrl;

    private int $timeoutSeconds;

    private ?int $billedCharacters = null;

    private bool $complete = false;

    public function __construct(string $documentUrl, int $timeoutSeconds = 120)
    {
        if ($timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('Document workflow timeout must be a positive integer in seconds.');
        }

        $this->documentUrl = $documentUrl;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * @param HttpResponse $upload Already executed document upload response.
     * @param callable(HttpRequest):HttpResponse $request Authenticated HTTP transport.
     * @return HttpResponse
     */
    public function complete(array $upload, callable $request): array
    {
        $this->billedCharacters = null;
        $this->complete = false;
        $started = $this->clock();
        $deadline = $started + $this->timeoutSeconds;
        $logs = [];

        $uploadBody = $upload['body'];
        $credentials = [];
        $decoded = null;
        try {
            $decoded = json_decode($uploadBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            // The status error below remains useful even when a provider sends a
            // non-JSON error body; successful uploads still require valid JSON.
        }
        if (is_array($decoded)) {
            $candidateId = $decoded['document_id'] ?? null;
            $candidateKey = $decoded['document_key'] ?? null;
            if (is_string($candidateId) && $candidateId !== '') {
                $credentials[] = $candidateId;
            }
            if (is_string($candidateKey) && $candidateKey !== '') {
                $credentials[] = $candidateKey;
            }
        }
        if (!is_array($decoded)) {
            $upload['debugRequest'] = '';
            $upload['debugResponse'] = '[Malformed document upload response omitted]';
        }
        $this->appendLogs($logs, $upload, $credentials);
        if ($upload['status'] < 200 || $upload['status'] >= 300) {
            throw $this->failure('Document upload failed with HTTP ' . $upload['status'], $logs, $credentials);
        }
        if (!is_array($decoded)) {
            throw $this->failure('Document upload returned malformed JSON', $logs, $credentials);
        }

        $id = $decoded['document_id'] ?? null;
        $key = $decoded['document_key'] ?? null;
        if (!is_string($id) || $id === '' || !is_string($key) || $key === '') {
            throw $this->failure('Document upload response did not contain a document ID and key', $logs, $credentials);
        }
        $credentials = [$id, $key];
        // Re-sanitize the initial response after extracting credentials. A transport may
        // have supplied unmasked debug fields even when its response body is structured.
        $logs = [];
        $this->appendLogs($logs, $upload, $credentials);

        while (true) {
            $timeout = $this->remainingTimeout($deadline, $logs, $credentials);
            $pollUrl = rtrim($this->documentUrl, '/') . '/' . rawurlencode($id);
            $pollBody = http_build_query(['document_key' => $key], '', '&', PHP_QUERY_RFC3986);
            $pollRequest = [
                'url' => $pollUrl,
                'body' => $pollBody,
                'headers' => ['Content-Type: application/x-www-form-urlencoded'],
                'maskPatterns' => $this->maskPatterns($credentials),
                'timeoutSeconds' => $timeout,
            ];

            try {
                /** @var HttpResponse $poll */
                $poll = $request($pollRequest);
            } catch (\Throwable $e) {
                throw $this->failure('Document status request failed: ' . $this->safeMessage($e), $logs, $credentials);
            }
            $this->appendLogs($logs, $poll, $credentials);
            if ($poll['status'] < 200 || $poll['status'] >= 300) {
                throw $this->failure(
                    'Document status request failed with HTTP ' . $poll['status'],
                    $logs,
                    $credentials
                );
            }

            try {
                $statusPayload = json_decode($poll['body'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                throw $this->failure('Document status response was malformed JSON', $logs, $credentials);
            }
            if (!is_array($statusPayload) || !is_string($statusPayload['status'] ?? null)) {
                throw $this->failure('Document status response was malformed', $logs, $credentials);
            }
            $status = $statusPayload['status'];
            if ($status === 'error') {
                $detail = $statusPayload['error_message'] ?? 'provider processing error';
                $detail = is_scalar($detail) ? (string)$detail : 'provider processing error';
                throw $this->failure(
                    'Document processing failed: ' . $detail,
                    $logs,
                    $credentials
                );
            }
            if ($status === 'done') {
                $billed = $statusPayload['billed_characters'] ?? null;
                $this->billedCharacters = is_int($billed) ? $billed : null;
                break;
            }
            if ($status !== 'queued' && $status !== 'translating') {
                throw $this->failure('Document status response contained an unsupported status', $logs, $credentials);
            }
            $this->pause();
        }

        $timeout = $this->remainingTimeout($deadline, $logs, $credentials);
        $resultUrl = rtrim($this->documentUrl, '/') . '/' . rawurlencode($id) . '/result';
        $resultBody = http_build_query(['document_key' => $key], '', '&', PHP_QUERY_RFC3986);
        $resultRequest = [
            'url' => $resultUrl,
            'body' => $resultBody,
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'maskPatterns' => $this->maskPatterns($credentials),
            'timeoutSeconds' => $timeout,
        ];
        try {
            /** @var HttpResponse $result */
            $result = $request($resultRequest);
        } catch (\Throwable $e) {
            throw $this->failure('Document download request failed: ' . $this->safeMessage($e), $logs, $credentials);
        }
        $this->appendLogs($logs, $result, $credentials);
        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw $this->failure('Document download failed with HTTP ' . $result['status'], $logs, $credentials);
        }

        $this->remainingTimeout($deadline, $logs, $credentials);
        $this->complete = true;
        return [
            'status' => $result['status'],
            'headers' => $result['headers'],
            'body' => $result['body'],
            'debugRequest' => implode('', array_column($logs, 'request')),
            'debugResponse' => implode('', array_column($logs, 'response')),
        ];
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function billedCharacters(): ?int
    {
        return $this->billedCharacters;
    }

    protected function clock(): float
    {
        return hrtime(true) / 1_000_000_000;
    }

    protected function pause(): void
    {
        usleep(1_000_000);
    }

    /**
     * @param array<int,array{request:string,response:string}> $logs
     * @param HttpResponse $response
     * @param array<int,string> $credentials
     */
    private function appendLogs(array &$logs, array $response, array $credentials): void
    {
        $logs[] = [
            'request' => $this->sanitize($response['debugRequest'], $credentials),
            'response' => $this->sanitize($response['debugResponse'], $credentials),
        ];
    }

    /**
     * @param array<int,string> $credentials
     * @return array<int,string>
     */
    private function maskPatterns(array $credentials): array
    {
        $patterns = [];
        foreach ($credentials as $credential) {
            $json = json_encode($credential, JSON_THROW_ON_ERROR);
            $variants = [$credential, rawurlencode($credential), urlencode($credential), substr($json, 1, -1)];
            foreach ($variants as $pattern) {
                if ($pattern !== '' && !in_array($pattern, $patterns, true)) {
                    $patterns[] = $pattern;
                }
            }
        }
        return $patterns;
    }

    /** @param array<int,string> $credentials */
    private function sanitize(string $value, array $credentials): string
    {
        return str_replace($this->maskPatterns($credentials), '***', $value);
    }

    /**
     * @param array<int,array{request:string,response:string}> $logs
     * @param array<int,string> $credentials
     */
    private function remainingTimeout(float $deadline, array $logs, array $credentials): int
    {
        $remaining = $deadline - $this->clock();
        if ($remaining <= 0) {
            throw $this->failure('Document workflow timed out', $logs, $credentials);
        }
        return max(1, (int)ceil($remaining));
    }

    /**
     * @param array<int,array{request:string,response:string}> $logs
     * @param array<int,string> $credentials
     */
    private function failure(string $message, array $logs, array $credentials): \RuntimeException
    {
        $log = '';
        foreach ($logs as $entry) {
            $log .= $this->sanitize($entry['request'] . $entry['response'], $credentials);
        }
        $message = $this->sanitize($message, $credentials);
        if ($log !== '') {
            $message .= "\n\n" . $log;
        }
        return new \RuntimeException($message);
    }

    private function safeMessage(\Throwable $e): string
    {
        return preg_replace('/[\r\n]+/', ' ', $e->getMessage()) ?? 'transport exception';
    }
}
