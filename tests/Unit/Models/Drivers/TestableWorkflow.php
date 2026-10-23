<?php

declare(strict_types=1);

namespace Bblslug\Tests\Unit\Models\Drivers;

use Bblslug\Models\Drivers\DeepLDocumentWorkflow;

final class TestableWorkflow extends DeepLDocumentWorkflow
{
    /** @var array<int,float> */
    private array $times;

    /** @param array<int,float> $times */
    public function __construct(string $url, int $timeout = 120, array $times = [0.0, 0.1, 0.2, 0.3, 0.4])
    {
        parent::__construct($url, $timeout);
        $this->times = $times;
    }

    protected function clock(): float
    {
        return count($this->times) > 1 ? (float)array_shift($this->times) : (float)$this->times[0];
    }

    protected function pause(): void
    {
    }
}
