<?php

namespace App\Services\Learning;

final readonly class ProgressSummary
{
    public int $completed;

    public int $total;

    public float $percent;

    public bool $isComplete;

    public string $state;

    public function __construct(int $completed, int $total, public ?int $nextLessonId = null)
    {
        $this->total = max(0, $total);
        $this->completed = max(0, min($completed, $this->total));
        $this->percent = self::percentage($this->completed, $this->total);
        $this->isComplete = $this->total > 0 && $this->completed === $this->total;
        $this->state = match (true) {
            $this->total === 0 => 'No published lessons',
            $this->isComplete => 'Complete',
            $this->completed === 0 => 'Not started',
            default => 'In progress',
        };
    }

    public static function percentage(int $completed, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(max(0, min(100, $completed / $total * 100)), 2);
    }
}
