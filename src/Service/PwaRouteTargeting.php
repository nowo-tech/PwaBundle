<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Service;

use function is_string;
use function preg_match;
use function rtrim;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function trim;

/**
 * Resolves whether PWA assets should load on the current route or path.
 */
final class PwaRouteTargeting
{
    public const MODE_ALL    = 'all';
    public const MODE_ONLY   = 'only';
    public const MODE_EXCEPT = 'except';

    public const MATCH_BY_NAME = 'name';
    public const MATCH_BY_PATH = 'path';

    /**
     * @param list<mixed> $patterns
     */
    public function shouldApply(
        string $currentRoute,
        string $currentPath,
        string $mode,
        array $patterns,
        string $matchBy = self::MATCH_BY_NAME,
    ): bool {
        $mode = trim($mode);
        if ($mode === self::MODE_ALL || $mode === '') {
            return true;
        }

        $normalizedPatterns = $this->normalizePatterns($patterns);
        $matches            = false;

        foreach ($normalizedPatterns as $pattern) {
            if ($matchBy === self::MATCH_BY_PATH) {
                if ($this->pathMatches($this->normalizePath($currentPath), $pattern)) {
                    $matches = true;

                    break;
                }

                continue;
            }

            if ($currentRoute !== '' && $currentRoute === $pattern) {
                $matches = true;

                break;
            }
        }

        return match ($mode) {
            self::MODE_ONLY   => $matches,
            self::MODE_EXCEPT => !$matches,
            default           => true,
        };
    }

    /**
     * @param list<mixed> $patterns
     *
     * @return list<string>
     */
    private function normalizePatterns(array $patterns): array
    {
        $normalized = [];
        foreach ($patterns as $pattern) {
            if (!is_string($pattern)) {
                continue;
            }

            $pattern = trim($pattern);
            if ($pattern !== '') {
                $normalized[] = $pattern;
            }
        }

        return $normalized;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return rtrim($path, '/') ?: '/';
    }

    private function pathMatches(string $path, string $pattern): bool
    {
        $pattern = trim($pattern);

        if (str_starts_with($pattern, '/') && str_ends_with($pattern, '/') && strlen($pattern) > 2) {
            return preg_match($pattern, $path) === 1;
        }

        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim($pattern, '*');
            if ($prefix === '') {
                return true;
            }

            $prefix = $this->normalizePath($prefix);

            return $path === $prefix || str_starts_with($path, $prefix . '/');
        }

        return $path === $this->normalizePath($pattern);
    }
}
