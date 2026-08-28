<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Service;

use Nowo\PwaBundle\Service\ServiceWorkerCacheDefaults;
use Nowo\PwaBundle\Service\ServiceWorkerScriptBuilder;
use PHPUnit\Framework\TestCase;

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

        self::assertStringContainsString('"precacheUrls":[]', $script);
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
        self::assertStringContainsString('async function putInCache(cache, request, response)', $script);
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
}
