<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ApplicationCache
{
    public const DASHBOARD_TTL = 300;

    public const EVENT_TTL = 300;

    public const REPORT_TTL = 600;

    public function rememberAdminDashboard(string $section, callable $callback): mixed
    {
        return $this->remember('admin', $section, self::DASHBOARD_TTL, $callback);
    }

    public function rememberCompany(int $companyId, string $section, callable $callback): mixed
    {
        return $this->remember("company:{$companyId}", $section, self::DASHBOARD_TTL, $callback);
    }

    public function rememberEvent(int $eventId, string $section, callable $callback, int $ttl = self::EVENT_TTL): mixed
    {
        return $this->remember("event:{$eventId}", $section, $ttl, $callback);
    }

    public function invalidateAdmin(): void
    {
        $this->advance('admin');
    }

    public function invalidateCompany(?int $companyId): void
    {
        if ($companyId) {
            $this->advance("company:{$companyId}");
        }

        $this->invalidateAdmin();
    }

    public function invalidateEvent(?int $eventId, ?int $companyId = null): void
    {
        if ($eventId) {
            $this->advance("event:{$eventId}");
        }

        $this->invalidateCompany($companyId);
    }

    private function remember(string $scope, string $section, int $ttl, callable $callback): mixed
{
    $version = Cache::rememberForever($this->versionKey($scope), fn () => 1);

    return Cache::remember("attendance:{$scope}:v{$version}:{$section}", $ttl, function () use ($callback) {
        $result = $callback();
        
        // Automatically convert collections to arrays for safe caching
        if ($result instanceof \Illuminate\Support\Collection) {
            return $result->toArray();
        }

        return $result;
    });
}
    private function advance(string $scope): void
    {
        $key = $this->versionKey($scope);
        Cache::forever($key, ((int) Cache::get($key, 1)) + 1);
    }

    private function versionKey(string $scope): string
    {
        return "attendance:{$scope}:version";
    }
}
