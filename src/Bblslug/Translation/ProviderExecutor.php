<?php

declare(strict_types=1);

namespace Bblslug\Translation;

use Bblslug\HttpClient;
use Bblslug\HttpWorkflowInterface;
use Bblslug\Models\ModelDriverInterface;
use Bblslug\OperationDeadline;
use Bblslug\SecretMasker;

/** @internal Executes provider exchanges within one translation operation. */
final class ProviderExecutor
{
    /**
     * @param array<string,mixed> $model
     * @param \Closure(string,string=):void $feedback
     */
    public function __construct(
        private readonly ModelDriverInterface $driver,
        private readonly array $model,
        private readonly string $modelKey,
        private readonly string $apiKey,
        private readonly ?string $proxy,
        private readonly \Closure $feedback,
        private readonly ?OperationDeadline $deadline
    ) {
    }

    /**
     * Execute one packet, including its request-local continuation.
     *
     * @param array<string,mixed> $options
     * @param array<string,mixed>|null $prebuiltRequest
     * @return array{text:string,usage:array<string,mixed>|null,httpStatus:int,debugRequest:string,
     *     debugResponse:string,rawResponseBody:string}
     */
    public function execute(
        string $requestText,
        array $options,
        string $validationLog,
        ?string $localOverride = null,
        ?array $prebuiltRequest = null
    ): array {
        $dryRun = (bool) ($options['dryRun'] ?? false);
        $verbose = (bool) ($options['verbose'] ?? false);
        $rawUsage = null;
        $endpoint = is_string($this->model['endpoint'] ?? null) ? $this->model['endpoint'] : '';
        // Build request
        ($this->feedback)("Building request for endpoint: {$endpoint}", 'info');
        /** @var array{url:mixed,body:mixed,headers:mixed,responseContext?:mixed,localResult?:string,workflow?:HttpWorkflowInterface,timeoutSeconds?:int} $reqRaw */
        $reqRaw = $prebuiltRequest ?? $this->driver->buildRequest(
            $this->model,
            $requestText,
            $options
        );

        if ($localOverride !== null) {
            $reqRaw['localResult'] = $localOverride;
        }
        $options['responseContext'] = $reqRaw['responseContext'] ?? null;
        $localResult = $reqRaw['localResult'] ?? null;

        // Normalize request shape
        $reqUrl = \is_string($reqRaw['url']  ?? null) ? (string)$reqRaw['url']  : '';
        $reqBody = \is_string($reqRaw['body'] ?? null) ? (string)$reqRaw['body'] : '';
        $reqHeaders = [];
        foreach ((array)($reqRaw['headers'] ?? []) as $h) {
            if (\is_string($h)) {
                $reqHeaders[] = $h;
            }
        }

        // API auth key handling
        $requirements = isset($this->model['requirements']) && \is_array($this->model['requirements'])
            ? $this->model['requirements']
            : null;
        $auth = \is_array($requirements) ? ($requirements['auth'] ?? null) : null;
        if (\is_array($auth)) {
            if (!$this->apiKey) {
                throw new \InvalidArgumentException("API key is required for {$this->modelKey}");
            }
            ($this->feedback)("Injecting auth credentials", 'info');
            $keyName = \is_string($auth['key_name'] ?? null) ? $auth['key_name'] : '';
            $prefix  = (\is_string($auth['prefix'] ?? null) && $auth['prefix'] !== '')
                ? $auth['prefix'] . ' '
                : '';
            $type = \is_string($auth['type'] ?? null) ? $auth['type'] : 'form';
            switch ($type) {
                case 'header':
                    $reqHeaders[] = "{$keyName}: {$prefix}{$this->apiKey}";
                    break;
                case 'form':
                    $reqBody .= '&' . urlencode($keyName) . '=' . urlencode($this->apiKey);
                    break;
                case 'query':
                    $sep = \str_contains($reqUrl, '?') ? '&' : '?';
                    $reqUrl .= $sep . http_build_query([$keyName => $this->apiKey]);
                    break;
                default:
                    throw new \RuntimeException("Unsupported auth type: {$type}");
            }
        }

        $maskPatterns = [
            $this->apiKey,
            $this->proxy ?? '',
            \is_string($options['folder_id'] ?? null) ? $options['folder_id'] : '',
        ];

        // Perform HTTP request
        $requestMessage = $dryRun ? "Dry-run: skipping HTTP request" : "Sending HTTP request";
        if ($localResult !== null) {
            $requestMessage = "No translatable string values: skipping HTTP request";
        }
        ($this->feedback)($requestMessage, 'info');
        try {
            $http = HttpClient::request(
                method: 'POST',
                url: $reqUrl,
                body: $reqBody,
                dryRun: $dryRun || $localResult !== null,
                headers: $reqHeaders,
                maskPatterns: $maskPatterns,
                proxy: $this->proxy,
                verbose: $verbose,
                timeoutSeconds: $reqRaw['timeoutSeconds'] ?? null,
                timeoutMilliseconds: $this->deadline?->remainingMilliseconds()
            );
        } catch (\RuntimeException $exception) {
            $this->deadline?->check();
            throw $exception;
        }

        $this->deadline?->check();
        $workflow = $reqRaw['workflow'] ?? null;
        if (!$dryRun && $localResult === null && $workflow instanceof HttpWorkflowInterface) {
            $proxy = $this->proxy;
            $deadline = $this->deadline;
            $http = $workflow->complete(
                $http,
                /**
                 * @param array{url:string,body:string,headers:array<int,string>,
                 *     maskPatterns:array<int,string>,timeoutSeconds:int} $next
                 * @return array{status:int,body:string,headers:array<string,array<int,string>>,
                 *     debugRequest:string,debugResponse:string}
                 */
                static function (array $next) use ($reqHeaders, $maskPatterns, $proxy, $verbose, $deadline): array {
                    $headers = array_values(array_filter(
                        $reqHeaders,
                        static fn (string $header): bool => stripos($header, 'Content-Type:') !== 0
                    ));
                    try {
                        return HttpClient::request(
                            method: 'POST',
                            url: $next['url'],
                            body: $next['body'],
                            headers: array_merge($headers, $next['headers']),
                            maskPatterns: array_merge($maskPatterns, $next['maskPatterns']),
                            proxy: $proxy,
                            verbose: $verbose,
                            timeoutSeconds: $next['timeoutSeconds'],
                            timeoutMilliseconds: $deadline?->remainingMilliseconds()
                        );
                    } catch (\RuntimeException $e) {
                        $deadline?->check();
                        throw new \RuntimeException(
                            (new SecretMasker($maskPatterns))->redact($e->getMessage()),
                            $e->getCode()
                        );
                    }
                }
            );
        }

        $this->deadline?->check();
        $httpStatus = $http['status'];
        $debugRequest = $validationLog . $http['debugRequest'];
        $debugResponse = $http['debugResponse'];
        $raw = $http['body'];

        $errorMasker = new SecretMasker($maskPatterns);

        // Parse response
        if ($dryRun || $localResult !== null) {
            $translated = $localResult ?? $requestText;
            $rawUsage   = null;
            ($this->feedback)($localResult !== null ? "Local translation completed" : "Dry-run completed", 'info');
        } else {
            ($this->feedback)("HTTP response received (status={$httpStatus})", 'info');
            $hasCustomHttpHandling = isset($this->model['http_error_handling'])
                && !empty($this->model['http_error_handling']);
            if ($httpStatus >= 400 && !$hasCustomHttpHandling) {
                throw new \RuntimeException(
                    $errorMasker->redact(
                        "HTTP {$httpStatus} error from {$reqUrl}: {$raw}\n\n" . $debugRequest . $debugResponse
                    )
                );
            }
            ($this->feedback)("Parsing provider response", 'info');
            try {
                /** @var array{text:string, usage?:array<string,mixed>|null} $parsed */
                $parsed     = $this->driver->parseResponse($this->model, $raw, $options);
                $translated = $parsed['text'];
                /** @var array<string,mixed>|null $rawUsage */
                $rawUsage   = $parsed['usage'] ?? null;
            } catch (\RuntimeException $e) {
                throw new \RuntimeException(
                    $errorMasker->redact(
                        $e->getMessage() . "\n\n" . $debugRequest . $debugResponse
                    )
                );
            }
            if (
                preg_match('/\A\s*\z/u', $requestText) !== 1
                && preg_match('/\A\s*\z/u', $translated) === 1
            ) {
                $message = 'Provider returned an empty translation for nonempty input';
                if ($verbose) {
                    $message .= "\n\n" . $debugRequest . $debugResponse;
                }
                throw new \RuntimeException($errorMasker->redact($message));
            }
            ($this->feedback)("Provider response parsed", 'info');
        }

        $this->deadline?->check();
        return [
            'text' => $translated,
            'usage' => $rawUsage,
            'httpStatus' => $httpStatus,
            'debugRequest' => $debugRequest,
            'debugResponse' => $debugResponse,
            'rawResponseBody' => $raw,
        ];
    }
}
