<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    /**
     * Dashboard cache keys
     */
    public static function dashboardKey(int $userId, ?array $projectIds = null): string
    {
        $scope = $projectIds ? md5(implode(',', $projectIds)) : 'all';
        return "dashboard:user:{$userId}:scope:{$scope}";
    }

    /**
     * Clear dashboard cache for a specific user
     */
    public static function forgetDashboard(int $userId, ?array $projectIds = null): void
    {
        Cache::forget(self::dashboardKey($userId, $projectIds));
        // Also clear the "all projects" scope
        Cache::forget(self::dashboardKey($userId));
    }

    /**
     * Clear dashboard cache for multiple users (e.g., after project status change)
     */
    public static function forgetDashboardForUsers(array $userIds): void
    {
        foreach ($userIds as $userId) {
            self::forgetDashboard($userId);
        }
    }

    /**
     * Report generation status cache
     */
    public static function reportStatusKey(string $reportType, int $userId): string
    {
        return "report:{$reportType}:user:{$userId}:status";
    }

    /**
     * Reference data cache (departments, roles, etc.)
     */
    public static function referenceKey(string $entity): string
    {
        return "reference:{$entity}";
    }

    /**
     * Cached dashboard counts with 60-second expiry
     */
    public static function rememberDashboard(callable $callback, int $userId, ?array $projectIds = null, int $ttl = 60): mixed
    {
        return Cache::remember(self::dashboardKey($userId, $projectIds), now()->addSeconds($ttl), $callback);
    }
}
