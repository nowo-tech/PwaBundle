<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Service;

use Nowo\PwaBundle\Service\ServiceWorkerCacheDefaults;
use Nowo\PwaBundle\Service\ServiceWorkerScriptBuilder;
use PHPUnit\Framework\TestCase;

use function substr_count;

final class ServiceWorkerScriptBuilderTest extends TestCase
{
    public function testGeneratesScriptWithConfig(): void
    {
        $builder = new ServiceWorkerScriptBuilder();
        $script  = $builder->build([
            'cache_version'          => 'v2',
            'strategy'               => 'cache-first',
            'precache_urls'          => ['/', '/offline'],
            'runtime_cache_patterns' => ['/assets/'],
            'skip_waiting'           => true,
            'clients_claim'          => true,
        ], 'https://example.test/offline');

        self::assertStringContainsString("'use strict'", $script);
        self::assertStringContainsString('cache-first', $script);
        self::assertStringContainsString('https://example.test/offline', $script);
        self::assertStringContainsString('"cacheVersion":"v2"', $script);
    }

    public function testIgnoresInvalidPrecacheList(): void
    {
        $builder = new ServiceWorkerScriptBuilder();
        $script  = $builder->build([
            'precache_urls'          => 'invalid',
            'runtime_cache_patterns' => ['', '/assets/'],
        ], '/offline');

        self::assertStringContainsString('"precacheUrls":["/offline"]', $script);
        self::assertStringContainsString('"/assets/"', $script);
    }

    public function testIncludesDenyPatternsAndCachePrefix(): void
    {
        $builder = new ServiceWorkerScriptBuilder();
        $script  = $builder->build([
            'cache_name_prefix'         => 'my-pwa',
            'deny_cache_patterns'       => ['/api/'],
            'navigation_preload'        => true,
            'runtime_cache_max_entries' => 50,
        ], '/offline');

        self::assertStringContainsString('"cachePrefix":"my-pwa"', $script);
        self::assertStringContainsString('"/api/"', $script);
        self::assertStringContainsString('navigationPreload', $script);
        self::assertStringContainsString('runtimeCacheMaxEntries', $script);
    }

    public function testSanitizesCachePrefix(): void
    {
        $script = (new ServiceWorkerScriptBuilder())->build([
            'cache_name_prefix' => 'bad prefix!',
        ], '/offline');

        self::assertStringContainsString('"cachePrefix":"badprefix"', $script);
    }

    public function testIgnoresNonHttpRequestsAndUnsafeCachePut(): void
    {
        $script = (new ServiceWorkerScriptBuilder())->build([
            'precache_urls' => ['/', '/offline'],
        ], '/offline');

        self::assertStringContainsString('function isHttpOrHttps(url)', $script);
        self::assertStringContainsString('function shouldHandleRequest(request)', $script);
        self::assertStringContainsString('function matchesPrecacheEntry(url, entry)', $script);
        self::assertStringContainsString('async function putInCache(request, response)', $script);
        self::assertStringContainsString('function isCacheableResponse(response)', $script);
        self::assertStringContainsString("cacheControl.includes('no-store')", $script);
        self::assertStringContainsString("cacheControl.includes('private')", $script);
        self::assertStringNotContainsString('url.includes(entry)', $script);
    }

    public function testFiltersDeniedUrlsFromPrecache(): void
    {
        $script = (new ServiceWorkerScriptBuilder())->build([
            'precache_urls'       => ['/', '/en/login', '/offline', '/es/register'],
            'deny_cache_patterns' => ServiceWorkerCacheDefaults::denyCachePatterns(),
        ], '/offline');

        self::assertStringContainsString('"/offline"', $script);
        self::assertStringNotContainsString('/en/login', $script);
        self::assertStringNotContainsString('/es/register', $script);
        self::assertStringContainsString('"precacheUrls":["/","/offline"]', $script);
    }

    public function testInstallFiltersDeniedPrecacheAtRuntime(): void
    {
        $script = (new ServiceWorkerScriptBuilder())->build([], '/offline');

        self::assertStringContainsString('CONFIG.precacheUrls.filter((url) => !isDenied(url))', $script);
    }

    public function testAppendsOptionalScript(): void
    {
        $script = (new ServiceWorkerScriptBuilder())->build([
            'append_script' => "/* push */\nself.addEventListener('push', () => {});",
        ], '/offline');

        self::assertStringContainsString('/* push */', $script);
        self::assertStringContainsString("self.addEventListener('push'", $script);
    }

    public function testOfflineUrlIsAlwaysPrecached(): void
    {
        $script = (new ServiceWorkerScriptBuilder())->build([
            'precache_urls' => ['/'],
        ], 'https://example.test/pwa/offline');

        self::assertStringContainsString('"precacheUrls":["/","https://example.test/pwa/offline"]', $script);
        self::assertStringContainsString('"offlineUrl":"https://example.test/pwa/offline"', $script);
        // Absolute precache entries are compared by pathname.
        self::assertStringContainsString('function entryPathname(entry)', $script);
    }

    public function testOfflineUrlIsNotDuplicatedInPrecache(): void
    {
        $script = (new ServiceWorkerScriptBuilder())->build([
            'precache_urls' => ['/', '/offline'],
        ], '/offline');

        self::assertStringContainsString('"precacheUrls":["/","/offline"]', $script);
    }

    public function testEmptyOfflineUrlIsNotPrecached(): void
    {
        $script = (new ServiceWorkerScriptBuilder())->build(['precache_urls' => ['/']], '');

        self::assertStringContainsString('"precacheUrls":["/"]', $script);
    }

    public function testRuntimeEntriesUseSeparateTrimmedCache(): void
    {
        $script = (new ServiceWorkerScriptBuilder())->build(['runtime_cache_max_entries' => 10], '/offline');

        self::assertStringContainsString('const RUNTIME_CACHE_NAME = `${CONFIG.cachePrefix}-runtime-${CONFIG.cacheVersion}`;', $script);
        self::assertStringContainsString('key !== CACHE_NAME && key !== RUNTIME_CACHE_NAME', $script);
        self::assertStringContainsString("const cache = await caches.open(RUNTIME_CACHE_NAME);\n    await cache.put(request, response.clone());\n    await trimRuntimeCache(cache);", $script);
        // The shell cache is never passed to trimRuntimeCache.
        self::assertStringNotContainsString('trimRuntimeCache(shell)', $script);
        self::assertSame(1, substr_count($script, 'await trimRuntimeCache('));
    }

    public function testNavigationPreloadResponseIsConsumed(): void
    {
        $script = (new ServiceWorkerScriptBuilder())->build(['navigation_preload' => true], '/offline');

        self::assertStringContainsString("CONFIG.navigationPreload && event.request.mode === 'navigate' ? event.preloadResponse : undefined", $script);
        self::assertStringContainsString('const preloaded = await preloadResponse;', $script);
        self::assertStringContainsString('const response = await fetchWithPreload(request, preloadResponse);', $script);
        self::assertStringContainsString('return networkFirst(event.request, preloadResponse);', $script);
    }

    public function testStaleWhileRevalidateHandlesNetworkFailure(): void
    {
        $script = (new ServiceWorkerScriptBuilder())->build(['strategy' => 'stale-while-revalidate'], '/offline');

        self::assertStringContainsString('}).catch(() => undefined);', $script);
        self::assertStringContainsString('waitUntil(networkPromise);', $script);
        self::assertStringContainsString('const fallback = await offlineFallback(request);' . "\n" . '  return fallback || Response.error();', $script);
        self::assertStringContainsString("if (request.mode === 'navigate' && CONFIG.offlineUrl) {", $script);
    }
}
