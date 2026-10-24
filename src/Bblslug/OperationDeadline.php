<?php

declare(strict_types=1);

namespace Bblslug;

/** A monotonic time budget shared by every request in one translation. */
final class OperationDeadline
{
    private float $started;
    /** @var \Closure():float */
    private \Closure $clock;

    /** @param callable():float|null $clock Internal clock override for deterministic checks. */
    public function __construct(private int $seconds, ?callable $clock = null)
    {
        if ($seconds < 1) {
            throw new \InvalidArgumentException('operationTimeoutSeconds must be a positive integer');
        }
        $this->clock = $clock !== null ? \Closure::fromCallable($clock)
            : static fn (): float => hrtime(true) / 1_000_000_000;
        $this->started = ($this->clock)();
    }

    public function check(): void
    {
        $this->remainingMilliseconds();
    }

    public function remainingMilliseconds(): int
    {
        $remaining = $this->seconds - (($this->clock)() - $this->started);
        if ($remaining < 0.001) {
            throw new \RuntimeException('Translation operation timeout exceeded', 28);
        }
        // Avoid integer overflow for deliberately very large budgets.
        if ($remaining >= intdiv(PHP_INT_MAX, 1000)) {
            return PHP_INT_MAX;
        }
        return (int) floor($remaining * 1000);
    }
}
