<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Service;

use function is_string;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strpbrk;
use function substr;
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

    /** Prefix marking a path pattern as a delimited PCRE (e.g. `regex:/^\/admin/`). */
    public const REGEX_PREFIX = 'regex:';

    /** Characters that make a legacy `/…/` pattern a regex rather than a plain path. */
    private const LEGACY_REGEX_SYNTAX = '^$\\()[]{}|+?*';

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

        $regex = $this->explicitRegex($pattern) ?? $this->legacySlashRegex($pattern);
        if ($regex !== null) {
            return $this->regexMatches($regex, $path);
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

    /**
     * Explicit regex markers: `regex:<delimited PCRE>` (e.g. `regex:/^\/admin/i`) or a `#…#` delimited PCRE
     * (e.g. `#^/admin(/|$)#`, flags allowed after the closing `#`).
     */
    private function explicitRegex(string $pattern): ?string
    {
        if (str_starts_with($pattern, self::REGEX_PREFIX)) {
            $regex = trim(substr($pattern, strlen(self::REGEX_PREFIX)));

            return $regex !== '' ? $regex : null;
        }

        if (preg_match('/^#.+#[a-zA-Z]*$/s', $pattern) === 1) {
            return $pattern;
        }

        return null;
    }

    /**
     * BC: before 1.9 every `/…/` pattern was a regex, so a plain path such as `/blog/` matched anywhere
     * (`/x/blog/y`). Now a `/…/` pattern is only treated as a regex when it contains regex syntax that
     * is not used in plain paths (`^ $ \ ( ) [ ] { } | + ? *`, or `.` when the body has no `/`, e.g. `/admin.+/` or `/blog.v2/`);
     * that legacy form is deprecated. A body with an unescaped `/` (e.g. `/a.b/c/`) is never a valid `/…/` regex,
     * so a `.` there is a literal path character.
     */
    private function legacySlashRegex(string $pattern): ?string
    {
        if (strlen($pattern) <= 2 || !str_starts_with($pattern, '/') || !str_ends_with($pattern, '/')) {
            return null;
        }

        $body = substr($pattern, 1, -1);
        if (strpbrk($body, self::LEGACY_REGEX_SYNTAX) === false && !(str_contains($body, '.') && !str_contains($body, '/'))) {
            return null;
        }

        trigger_deprecation(
            'nowo-tech/pwa-bundle',
            '1.9',
            'Path pattern "%s" relies on the implicit "/…/" regex syntax; prefix it with "regex:" (e.g. "regex:%s") or use a "#…#" delimited pattern.',
            $pattern,
            $pattern,
        );

        return $pattern;
    }

    /**
     * Invalid patterns never match and never raise a PHP warning.
     */
    private function regexMatches(string $regex, string $path): bool
    {
        // preg_match() returns false (and warns) on an invalid pattern: treat it as "no match", silently.
        $result = @preg_match($regex, $path);

        return $result === 1;
    }
}
