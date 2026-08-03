<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Service;

/**
 * Safe default deny patterns for the generated service worker.
 *
 * Patterns are matched with substring includes (PHP str_contains / JS url.includes),
 * so "/login" also matches locale-prefixed paths such as "/es/login".
 */
final class ServiceWorkerCacheDefaults
{
    /**
     * Paths that must never be stored in the Cache API by default.
     *
     * Host apps can replace this list via service_worker.deny_cache_patterns;
     * an explicit empty list disables the defaults.
     *
     * @return list<string>
     */
    public static function denyCachePatterns(): array
    {
        return [
            '/login',
            '/logout',
            '/register',
            '/reset-password',
            '/admin',
            '/api/',
            '/_profiler',
            '/_wdt',
            '/setup',
            '/_site_backup',
        ];
    }
}
