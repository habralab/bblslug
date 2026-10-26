<?php

declare(strict_types=1);

namespace Bblslug\Tests\Unit\Models\Drivers;

use Bblslug\Models\Drivers\DeepLDocumentWorkflow;
use Bblslug\OperationDeadline;
use PHPUnit\Framework\TestCase;

final class DeepLOperationDeadlineTest extends TestCase
{
    public function testExpiredUploadBudgetPreventsFirstStatusRequest(): void
    {
        $time = 0.0;
        $deadline = new OperationDeadline(1, static function () use (&$time): float {
            return $time;
        });
        $workflow = new DeepLDocumentWorkflow('https://example.test/document', 120, $deadline);
        $time = 1.1;
        $this->expectExceptionMessage('operation timeout');
        $workflow->complete($this->response('{"document_id":"id","document_key":"key"}'), static function (): array {
            self::fail('An expired upload budget must prevent polling');
        });
    }

    public function testStatusPauseUsesOnlyRemainingOperationTime(): void
    {
        $deadline = new OperationDeadline(1);
        $workflow = new DeepLDocumentWorkflow('https://example.test/document', 120, $deadline);
        $calls = 0;
        $began = microtime(true);
        try {
            $workflow->complete(
                $this->response('{"document_id":"id","document_key":"key"}'),
                function () use (&$calls): array {
                    ++$calls;
                    usleep(800_000);
                    return $this->response('{"status":"queued"}');
                }
            );
            self::fail('An unfinished document must time out');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('operation timeout', $e->getMessage());
            self::assertSame(28, $e->getCode());
            self::assertSame(1, $calls);
            self::assertLessThan(1.6, microtime(true) - $began);
            self::assertFalse($workflow->isComplete());
        }
    }

    private function response(string $body): array
    {
        return ['status' => 200, 'body' => $body, 'headers' => [], 'debugRequest' => '', 'debugResponse' => ''];
    }
}
