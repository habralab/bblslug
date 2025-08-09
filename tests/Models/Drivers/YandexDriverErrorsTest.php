<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use PHPUnit\Framework\TestCase;
use Bblslug\Models\Drivers\YandexDriver;
use RuntimeException;

/**
 * Yandex-specific error handling:
 * - folder-id mismatch (HTTP 400 with message mentioning folder ID)
 * - authentication error (401 or httpStatus "Unauthorized")
 * - internal server error (500)
 * - generic error passthrough
 */
final class YandexDriverErrorsTest extends TestCase
{
    /** @test */
    public function folderIdMismatchIsSurfaced(): void
    {
        $driver = new YandexDriver();
        $json = json_encode([
            'error' => [
                'httpCode' => 400,
                'message'  => 'folder ID does not match project',
            ],
        ], JSON_UNESCAPED_UNICODE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/folder-id mismatch/i');
        $driver->parseResponse([], $json);
    }

    /** @test */
    public function authenticationError401(): void
    {
        $driver = new YandexDriver();
        $json = json_encode([
            'error' => [
                'httpCode' => 401,
                'message'  => 'Unauthorized',
            ],
        ], JSON_UNESCAPED_UNICODE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/authentication error/i');
        $driver->parseResponse([], $json);
    }

    /** @test */
    public function authenticationErrorByHttpStatusText(): void
    {
        $driver = new YandexDriver();
        $json = json_encode([
            'error' => [
                'httpStatus' => 'Unauthorized',
                'message'    => 'Token invalid',
            ],
        ], JSON_UNESCAPED_UNICODE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/authentication error/i');
        $driver->parseResponse([], $json);
    }

    /** @test */
    public function internalServerError500(): void
    {
        $driver = new YandexDriver();
        $json = json_encode([
            'error' => [
                'httpCode' => 500,
                'message'  => 'Internal error',
            ],
        ], JSON_UNESCAPED_UNICODE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/internal server error/i');
        $driver->parseResponse([], $json);
    }

    /** @test */
    public function genericApiErrorIsPassedThrough(): void
    {
        $driver = new YandexDriver();
        $json = json_encode([
            'error' => [
                'httpCode' => 429,
                'message'  => 'Rate limit exceeded',
            ],
        ], JSON_UNESCAPED_UNICODE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Yandex API error .*Rate limit exceeded/i');
        $driver->parseResponse([], $json);
    }
}
