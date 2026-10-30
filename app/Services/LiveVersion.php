<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A marker that changes whenever an event's (or a company's) people, registrations or rooms
 * change. Open pages compare it every few seconds (resources/js/live-updates.js) and redraw
 * themselves when it moves, so an edit shows everywhere without reloading. Check-ins and meal
 * scans are left out: their pages update on their own, and they happen too often.
 */
class LiveVersion
{
    public function event(int $eventId): string
    {
        return (string) Cache::get("live:event:{$eventId}", '0');
    }

    public function company(int $companyId): string
    {
        return (string) Cache::get("live:company:{$companyId}", '0');
    }

    /** @var array<string, float> When this process last wrote each marker. */
    private array $written = [];

    /** @var array<string, true> Markers changed since they were last written, written by flush(). */
    private array $pending = [];

    public function touchEvent(?int $eventId): void
    {
        if ($eventId) {
            $this->touch("live:event:{$eventId}");
        }
    }

    public function touchCompany(?int $companyId): void
    {
        if ($companyId) {
            $this->touch("live:company:{$companyId}");
        }
    }

    /** Write every marker changed since its last write; run at the end of each request and job. */
    public function flush(): void
    {
        foreach (array_keys($this->pending) as $key) {
            $this->write($key);
        }
    }

    /**
     * A large import changes hundreds of people in one go: the marker is written at most every
     * couple of seconds, and once more when the request or job ends (flush), so the last
     * change is never missed.
     */
    private function touch(string $key): void
    {
        if (microtime(true) - ($this->written[$key] ?? 0) < 2) {
            $this->pending[$key] = true;

            return;
        }
        $this->write($key);
    }

    private function write(string $key): void
    {
        Cache::forever($key, (string) Str::ulid());
        $this->written[$key] = microtime(true);
        unset($this->pending[$key]);
    }
}
