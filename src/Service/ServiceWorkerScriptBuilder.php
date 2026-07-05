<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Service;

use function is_array;
use function is_string;
use function json_encode;
use function str_replace;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Generates a configurable service worker script from bundle settings.
 */
final class ServiceWorkerScriptBuilder
{
    /**
     * @param array<string, mixed> $serviceWorkerConfig
     */
    public function build(array $serviceWorkerConfig, string $offlineUrl): string
    {
        $prefix = (string) ($serviceWorkerConfig['cache_name_prefix'] ?? 'nowo-pwa');
        $prefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix) ?: 'nowo-pwa';

        $config = [
            'cachePrefix'            => $prefix,
            'cacheVersion'           => (string) ($serviceWorkerConfig['cache_version'] ?? 'v1'),
            'strategy'               => (string) ($serviceWorkerConfig['strategy'] ?? 'network-first'),
            'precacheUrls'           => $this->stringList($serviceWorkerConfig['precache_urls'] ?? []),
            'runtimeCachePatterns'   => $this->stringList($serviceWorkerConfig['runtime_cache_patterns'] ?? []),
            'denyCachePatterns'      => $this->stringList($serviceWorkerConfig['deny_cache_patterns'] ?? []),
            'offlineUrl'             => $offlineUrl,
            'skipWaiting'            => (bool) ($serviceWorkerConfig['skip_waiting'] ?? true),
            'clientsClaim'           => (bool) ($serviceWorkerConfig['clients_claim'] ?? true),
            'navigationPreload'      => (bool) ($serviceWorkerConfig['navigation_preload'] ?? false),
            'runtimeCacheMaxEntries' => (int) ($serviceWorkerConfig['runtime_cache_max_entries'] ?? 0),
        ];

        $json = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return str_replace('__CONFIG__', $json, $this->template());
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $list = [];
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $list[] = $value;
            }
        }

        return $list;
    }

    private function template(): string
    {
        return <<<'JS'
'use strict';
const CONFIG = __CONFIG__;
const CACHE_NAME = `${CONFIG.cachePrefix}-${CONFIG.cacheVersion}`;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(CONFIG.precacheUrls)).then(() => {
      if (CONFIG.skipWaiting) {
        return self.skipWaiting();
      }
      return undefined;
    }),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.filter((key) => key.startsWith(`${CONFIG.cachePrefix}-`) && key !== CACHE_NAME).map((key) => caches.delete(key)),
    )).then(async () => {
      if (CONFIG.navigationPreload && self.registration.navigationPreload) {
        await self.registration.navigationPreload.enable();
      }
      if (CONFIG.clientsClaim) {
        return self.clients.claim();
      }
      return undefined;
    }),
  );
});

function isDenied(url) {
  return CONFIG.denyCachePatterns.some((pattern) => url.includes(pattern));
}

function isHttpOrHttps(url) {
  return url.startsWith('http://') || url.startsWith('https://');
}

function requestPathname(url) {
  try {
    return new URL(url).pathname;
  } catch (error) {
    return '';
  }
}

function matchesPrecacheEntry(url, entry) {
  const pathname = requestPathname(url);
  if (entry === '/' || entry === '') {
    return pathname === '/' || pathname === '';
  }

  return pathname === entry || pathname.endsWith(entry);
}

function matchesRuntimePattern(url) {
  return CONFIG.runtimeCachePatterns.some((pattern) => url.includes(pattern));
}

function shouldHandleRequest(request) {
  if (!isHttpOrHttps(request.url) || isDenied(request.url)) {
    return false;
  }

  if (matchesRuntimePattern(request.url)) {
    return true;
  }

  if (CONFIG.precacheUrls.some((entry) => matchesPrecacheEntry(request.url, entry))) {
    return true;
  }

  return request.mode === 'navigate';
}

async function trimRuntimeCache(cache) {
  if (!CONFIG.runtimeCacheMaxEntries || CONFIG.runtimeCacheMaxEntries <= 0) {
    return;
  }
  const keys = await cache.keys();
  if (keys.length <= CONFIG.runtimeCacheMaxEntries) {
    return;
  }
  const excess = keys.length - CONFIG.runtimeCacheMaxEntries;
  await Promise.all(keys.slice(0, excess).map((request) => cache.delete(request)));
}

async function putInCache(cache, request, response) {
  if (!response || response.status !== 200 || !isHttpOrHttps(request.url) || isDenied(request.url)) {
    return;
  }

  try {
    await cache.put(request, response.clone());
    await trimRuntimeCache(cache);
  } catch (error) {
    // Opaque or non-cacheable responses (e.g. chrome-extension://) are ignored.
  }
}

async function networkFirst(request) {
  try {
    const response = await fetch(request);
    if (response) {
      const cache = await caches.open(CACHE_NAME);
      await putInCache(cache, request, response);
    }
    return response;
  } catch (error) {
    const cached = await caches.match(request);
    if (cached) {
      return cached;
    }
    if (request.mode === 'navigate' && CONFIG.offlineUrl) {
      return caches.match(CONFIG.offlineUrl);
    }
    throw error;
  }
}

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) {
    return cached;
  }
  const response = await fetch(request);
  if (response) {
    const cache = await caches.open(CACHE_NAME);
    await putInCache(cache, request, response);
  }
  return response;
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(CACHE_NAME);
  const cached = await cache.match(request);
  const networkPromise = fetch(request).then(async (response) => {
    await putInCache(cache, request, response);
    return response;
  });
  return cached || networkPromise;
}

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET' || !shouldHandleRequest(event.request)) {
    return;
  }

  event.respondWith((async () => {
    switch (CONFIG.strategy) {
      case 'cache-first':
        return cacheFirst(event.request);
      case 'stale-while-revalidate':
        return staleWhileRevalidate(event.request);
      default:
        return networkFirst(event.request);
    }
  })());
});
JS;
    }
}
