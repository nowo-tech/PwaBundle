<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Service;

use function in_array;
use function is_string;

/**
 * Resolves whether PWA assets should load on the current route.
 */
final class PwaRouteTargeting
{
    public const MODE_ALL    = 'all';
    public const MODE_ONLY   = 'only';
    public const MODE_EXCEPT = 'except';

    /**
     * @param list<mixed> $targetRoutes
     */
    public function shouldApply(string $currentRoute, string $mode, array $targetRoutes): bool
    {
        $currentRoute = trim($currentRoute);
        $normalized   = [];
        foreach ($targetRoutes as $route) {
            if (!is_string($route)) {
                continue;
            }

            $route = trim($route);
            if ($route !== '') {
                $normalized[] = $route;
            }
        }

        $targetRoutes = $normalized;

        return match ($mode) {
            self::MODE_ONLY   => $currentRoute !== '' && in_array($currentRoute, $targetRoutes, true),
            self::MODE_EXCEPT => $currentRoute === '' || !in_array($currentRoute, $targetRoutes, true),
            default           => true,
        };
    }
}
