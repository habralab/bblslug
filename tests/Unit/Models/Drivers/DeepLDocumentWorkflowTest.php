<?php

declare(strict_types=1);

namespace Bblslug\Tests\Unit\Models\Drivers;

use Bblslug\Models\Drivers\DeepLDocumentWorkflow;
use PHPUnit\Framework\TestCase;

final class DeepLDocumentWorkflowTest extends TestCase
{
    public function testQueuedTranslatingDoneDownloadsRawBytesAndAggregatesLogs(): void
    {
        $requests = [];
        $responses = [
            $this->response(200, '{"status":"queued"}', "poll queued SECRET"),
            $this->response(200, '{"status":"translating"}', "poll translating SECRET"),
            $this->response(200, '{"status":"done","billed_characters":42}', "poll done SECRET"),
            $this->response(200, "\x00\xffraw", "download SECRET"),
        ];
        $workflow = new TestableWorkflow('https://example.test/v2/document', 10);
        $upload = $this->response(
            201,
            '{"document_id":"doc/secret","document_key":"key+secret"}',
            'upload doc/secret key+secret'
        );

        $result = $workflow->complete($upload, function (array $request) use (&$requests, &$responses): array {
            $requests[] = $request;
            return array_shift($responses);
        });

        self::assertSame("\x00\xffraw", $result['body']);
        self::assertSame(200, $result['status']);
        self::assertSame(42, $workflow->billedCharacters());
        self::assertCount(4, $requests);
        self::assertSame('https://example.test/v2/document/doc%2Fsecret', $requests[0]['url']);
        self::assertSame('document_key=key%2Bsecret', $requests[0]['body']);
        self::assertSame(['Content-Type: application/x-www-form-urlencoded'], $requests[0]['headers']);
        self::assertSame('https://example.test/v2/document/doc%2Fsecret/result', $requests[3]['url']);
        self::assertSame(10, $requests[0]['timeoutSeconds']);
        self::assertStringNotContainsString('doc/secret', $result['debugRequest'] . $result['debugResponse']);
        self::assertStringNotContainsString('key+secret', $result['debugRequest'] . $result['debugResponse']);
    }

    public function testUploadAndProviderFailuresAreClearAndRedacted(): void
    {
        $workflow = new TestableWorkflow('https://example.test/document');
        $upload = $this->response(
            200,
            '{"document_id":"id-secret","document_key":"key-secret"}',
            'id-secret key-secret'
        );

        $this->expectExceptionMessage('Document processing failed');
        try {
            $workflow->complete($upload, function (): array {
                return $this->response(
                    200,
                    '{"status":"error","error_message":"id-secret key-secret"}',
                    'id-secret key-secret'
                );
            });
        } catch (\RuntimeException $e) {
            self::assertStringNotContainsString('id-secret', $e->getMessage());
            self::assertStringNotContainsString('key-secret', $e->getMessage());
            throw $e;
        }
    }

    /** @dataProvider malformedProvider */
    public function testMalformedOrHttpResponsesFail(string $body, int $status, string $message): void
    {
        $workflow = new TestableWorkflow('https://example.test/document');
        $upload = $this->response(200, '{"document_id":"id","document_key":"key"}', '');
        $this->expectExceptionMessage($message);
        $workflow->complete($upload, function () use ($body, $status): array {
            return $this->response($status, $body, '');
        });
    }

    /** @return array<string,array{string,int,string}> */
    public static function malformedProvider(): array
    {
        return [
            'malformed json' => ['{', 200, 'malformed JSON'],
            'unknown status' => ['{"status":"finished"}', 200, 'unsupported status'],
            'poll http error' => ['{}', 503, 'HTTP 503'],
        ];
    }

    public function testTimeoutMakesNoRequestAndTransportErrorsAreSanitized(): void
    {
        $workflow = new TestableWorkflow('https://example.test/document', 1, [0.0, 2.0]);
        $upload = $this->response(200, '{"document_id":"id","document_key":"key"}', '');
        $this->expectExceptionMessage('timed out');
        $workflow->complete($upload, function (): array {
            self::fail('deadline should prevent polling');
        });
    }

    public function testInvalidTimeoutIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DeepLDocumentWorkflow('https://example.test/document', 0);
    }

    public function testUploadHttpErrorAndMalformedOrMissingCredentialsFail(): void
    {
        $workflow = new TestableWorkflow('https://example.test/document');
        try {
            $workflow->complete(
                $this->response(400, '{"document_id":"id-secret","document_key":"key-secret"}', 'key-secret'),
                static function (): array {
                    self::fail('upload response is already executed');
                }
            );
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('HTTP 400', $e->getMessage());
            self::assertStringNotContainsString('key-secret', $e->getMessage());
        }

        foreach (['{', '{"document_id":"id"}', '{"document_key":"key"}'] as $body) {
            try {
                $workflow->complete($this->response(200, $body, ''), static function (): array {
                    self::fail('upload response should fail before polling');
                });
                self::fail('expected upload validation failure');
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('Document upload', $e->getMessage());
            }
        }
    }

    public function testMissingPollStatusAndDownloadHttpErrorFail(): void
    {
        $upload = $this->response(200, '{"document_id":"id","document_key":"key"}', '');
        $workflow = new TestableWorkflow('https://example.test/document');
        $this->expectExceptionMessage('malformed');
        $workflow->complete($upload, function (): array {
            return $this->response(200, '{}', '');
        });
    }

    public function testDownloadHttpErrorDoesNotRetry(): void
    {
        $upload = $this->response(200, '{"document_id":"id","document_key":"key"}', '');
        $workflow = new TestableWorkflow('https://example.test/document');
        $calls = 0;
        try {
            $workflow->complete($upload, function () use (&$calls): array {
                $calls++;
                return $calls === 1
                    ? $this->response(200, '{"status":"done"}', '')
                    : $this->response(502, 'bad', '');
            });
            self::fail('expected download failure');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('HTTP 502', $e->getMessage());
            self::assertSame(2, $calls);
        }
    }

    public function testTransportExceptionsAreRedactedAndNotChained(): void
    {
        $upload = $this->response(200, '{"document_id":"id-secret","document_key":"key-secret"}', '');
        foreach ([true, false] as $pollFailure) {
            $workflow = new TestableWorkflow('https://example.test/document');
            $calls = 0;
            try {
                $workflow->complete($upload, function () use (&$calls, $pollFailure): array {
                    $calls++;
                    if (($pollFailure && $calls === 1) || (!$pollFailure && $calls === 2)) {
                        throw new \RuntimeException('key-secret transport detail');
                    }
                    return $this->response(200, '{"status":"done"}', '');
                });
                self::fail('expected transport failure');
            } catch (\RuntimeException $e) {
                self::assertStringNotContainsString('key-secret', $e->getMessage());
                self::assertNull($e->getPrevious());
            }
        }
    }

    public function testTimeoutCanOccurWhileQueuedOrBeforeDownload(): void
    {
        $upload = $this->response(200, '{"document_id":"id","document_key":"key"}', '');
        foreach (
            [
            [[0.0, 0.1, 2.0], '{"status":"queued"}'],
            [[0.0, 0.1, 2.0], '{"status":"done"}'],
            ] as [$times, $status]
        ) {
            $workflow = new TestableWorkflow('https://example.test/document', 1, $times);
            $calls = 0;
            try {
                $workflow->complete($upload, function () use (&$calls, $status): array {
                    $calls++;
                    return $this->response(200, $status, '');
                });
                self::fail('expected timeout');
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('timed out', $e->getMessage());
                self::assertSame(1, $calls);
            }
        }
    }

    public function testCompletionFlagAndEncodedCredentialsAreProtected(): void
    {
        $id = 'doc/"secret';
        $key = 'key+\\"secret';
        $body = json_encode(['document_id' => $id, 'document_key' => $key], JSON_THROW_ON_ERROR);
        $debug = $body . rawurlencode($id) . urlencode($key);
        $workflow = new TestableWorkflow('https://example.test/document');
        self::assertFalse($workflow->isComplete());
        $calls = 0;
        $result = $workflow->complete(
            $this->response(200, $body, $debug),
            function () use (&$calls, $debug): array {
                return $this->response(200, ++$calls === 1 ? '{"status":"done"}' : '{"label":"Hallo"}', $debug);
            }
        );
        self::assertTrue($workflow->isComplete());
        foreach ([$id, $key, rawurlencode($id), urlencode($key), substr(json_encode($key), 1, -1)] as $secret) {
            self::assertStringNotContainsString($secret, $result['debugRequest'] . $result['debugResponse']);
        }
        try {
            $workflow->complete($this->response(400, '{}', ''), static function (): array {
                self::fail('Upload failed');
            });
            self::fail('Expected upload error');
        } catch (\RuntimeException) {
            self::assertFalse($workflow->isComplete());
        }
    }

    public function testMalformedUploadDoesNotExposeUnparseableCredentials(): void
    {
        $workflow = new TestableWorkflow('https://example.test/document');
        try {
            $workflow->complete(
                $this->response(200, '{"document_key":"SECRET",', 'SECRET'),
                static function (): array {
                    self::fail('Malformed upload must stop before polling');
                }
            );
            self::fail('Expected malformed upload error');
        } catch (\RuntimeException $e) {
            self::assertStringNotContainsString('SECRET', $e->getMessage());
        }
    }

    /** @return array{status:int,body:string,headers:array<string,array<int,string>>,debugRequest:string,debugResponse:string} */
    private function response(int $status, string $body, string $debug): array
    {
        return [
            'status' => $status,
            'body' => $body,
            'headers' => ['X-Test' => ['ok']],
            'debugRequest' => $debug,
            'debugResponse' => $debug,
        ];
    }
}
