<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Service;

use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function str_contains;
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

        $denyPatterns = $this->stringList($serviceWorkerConfig['deny_cache_patterns'] ?? []);
        $precacheUrls = $this->stringList($serviceWorkerConfig['precache_urls'] ?? []);
        // The offline page must be precached or the navigation fallback can never be served.
        if ($offlineUrl !== '' && !in_array($offlineUrl, $precacheUrls, true)) {
            $precacheUrls[] = $offlineUrl;
        }
        $precacheUrls = $this->filterDeniedUrls($precacheUrls, $denyPatterns);

        $config = [
            'cachePrefix'            => $prefix,
            'cacheVersion'           => (string) ($serviceWorkerConfig['cache_version'] ?? 'v1'),
            'strategy'               => (string) ($serviceWorkerConfig['strategy'] ?? 'network-first'),
            'precacheUrls'           => $precacheUrls,
            'runtimeCachePatterns'   => $this->stringList($serviceWorkerConfig['runtime_cache_patterns'] ?? []),
            'denyCachePatterns'      => $denyPatterns,
            'offlineUrl'             => $offlineUrl,
            'skipWaiting'            => (bool) ($serviceWorkerConfig['skip_waiting'] ?? true),
            'clientsClaim'           => (bool) ($serviceWorkerConfig['clients_claim'] ?? true),
            'navigationPreload'      => (bool) ($serviceWorkerConfig['navigation_preload'] ?? false),
            'runtimeCacheMaxEntries' => (int) ($serviceWorkerConfig['runtime_cache_max_entries'] ?? 0),
        ];

        $json = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $script = str_replace('__CONFIG__', $json, $this->template());
        $append = trim((string) ($serviceWorkerConfig['append_script'] ?? ''));

        if ($append !== '') {
            $script .= "\n" . $append;
        }

        return $script;
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

    /**
     * @param list<string> $urls
     * @param list<string> $denyPatterns
     *
     * @return list<string>
     */
    private function filterDeniedUrls(array $urls, array $denyPatterns): array
    {
        if ($denyPatterns === []) {
            return $urls;
        }

        $filtered = [];
        foreach ($urls as $url) {
            if (!$this->matchesDenyPattern($url, $denyPatterns)) {
                $filtered[] = $url;
            }
        }

        return $filtered;
    }

    /**
     * @param list<string> $denyPatterns
     */
    private function matchesDenyPattern(string $url, array $denyPatterns): bool
    {
        foreach ($denyPatterns as $pattern) {
            if (str_contains($url, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function template(): string
    {
        return <<<'JS'
'use strict';
const CONFIG = __CONFIG__;
const CACHE_NAME = `${CONFIG.cachePrefix}-${CONFIG.cacheVersion}`;
// Runtime entries live in their own cache so trimming never evicts the precached shell / offline page.
const RUNTIME_CACHE_NAME = `${CONFIG.cachePrefix}-runtime-${CONFIG.cacheVersion}`;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      const urls = CONFIG.precacheUrls.filter((url) => !isDenied(url));
      return cache.addAll(urls);
    }).then(() => {
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
      keys.filter((key) => key.startsWith(`${CONFIG.cachePrefix}-`) && key !== CACHE_NAME && key !== RUNTIME_CACHE_NAME).map((key) => caches.delete(key)),
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

function isCacheableResponse(response) {
  if (!response || response.status !== 200) {
    return false;
  }

  // Honor HTTP cache directives: Symfony session pages typically send "private".
  // Caching them causes stale login/admin HTML and auth redirect loops.
  const cacheControl = (response.headers.get('Cache-Control') || '').toLowerCase();
  if (cacheControl.includes('no-store') || cacheControl.includes('private')) {
    return false;
  }

  return true;
}

function requestPathname(url) {
  try {
    return new URL(url).pathname;
  } catch (error) {
    return '';
  }
}

function entryPathname(entry) {
  if (!isHttpOrHttps(entry)) {
    return entry;
  }

  return requestPathname(entry);
}

function matchesPrecacheEntry(url, entry) {
  const pathname = requestPathname(url);
  const entryPath = entryPathname(entry);
  if (entryPath === '/' || entryPath === '') {
    return pathname === '/' || pathname === '';
  }

  return pathname === entryPath || pathname.endsWith(entryPath);
}

function isPrecacheRequest(url) {
  return CONFIG.precacheUrls.some((entry) => matchesPrecacheEntry(url, entry));
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

  if (isPrecacheRequest(request.url)) {
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

async function putInCache(request, response) {
  if (!response || !isHttpOrHttps(request.url) || isDenied(request.url) || !isCacheableResponse(response)) {
    return;
  }

  try {
    // Precached URLs are refreshed in place in the shell cache (never trimmed); everything else is runtime.
    if (isPrecacheRequest(request.url)) {
      const shell = await caches.open(CACHE_NAME);
      await shell.put(request, response.clone());
      return;
    }
    const cache = await caches.open(RUNTIME_CACHE_NAME);
    await cache.put(request, response.clone());
    await trimRuntimeCache(cache);
  } catch (error) {
    // Opaque or non-cacheable responses (e.g. chrome-extension://) are ignored.
  }
}

async function fetchWithPreload(request, preloadResponse) {
  if (preloadResponse) {
    try {
      const preloaded = await preloadResponse;
      if (preloaded) {
        return preloaded;
      }
    } catch (error) {
      // Navigation preload failed: fall back to a regular fetch.
    }
  }

  return fetch(request);
}

async function offlineFallback(request) {
  if (request.mode === 'navigate' && CONFIG.offlineUrl) {
    return caches.match(CONFIG.offlineUrl);
  }
  return undefined;
}

async function networkFirst(request, preloadResponse) {
  try {
    const response = await fetchWithPreload(request, preloadResponse);
    await putInCache(request, response);
    return response;
  } catch (error) {
    const cached = await caches.match(request);
    if (cached) {
      return cached;
    }
    const fallback = await offlineFallback(request);
    if (fallback) {
      return fallback;
    }
    throw error;
  }
}

async function cacheFirst(request, preloadResponse) {
  const cached = await caches.match(request);
  if (cached) {
    return cached;
  }
  try {
    const response = await fetchWithPreload(request, preloadResponse);
    await putInCache(request, response);
    return response;
  } catch (error) {
    const fallback = await offlineFallback(request);
    if (fallback) {
      return fallback;
    }
    throw error;
  }
}

async function staleWhileRevalidate(request, preloadResponse, waitUntil) {
  const cached = await caches.match(request);
  const networkPromise = fetchWithPreload(request, preloadResponse).then(async (response) => {
    await putInCache(request, response);
    return response;
  }).catch(() => undefined);
  if (cached) {
    waitUntil(networkPromise);
    return cached;
  }
  const response = await networkPromise;
  if (response) {
    return response;
  }
  const fallback = await offlineFallback(request);
  return fallback || Response.error();
}

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET' || !shouldHandleRequest(event.request)) {
    return;
  }

  // Consume the navigation preload response (when enabled) instead of issuing a second request.
  const preloadResponse = CONFIG.navigationPreload && event.request.mode === 'navigate' ? event.preloadResponse : undefined;
  const waitUntil = (promise) => event.waitUntil(promise);

  event.respondWith((async () => {
    switch (CONFIG.strategy) {
      case 'cache-first':
        return cacheFirst(event.request, preloadResponse);
      case 'stale-while-revalidate':
        return staleWhileRevalidate(event.request, preloadResponse, waitUntil);
      default:
        return networkFirst(event.request, preloadResponse);
    }
  })());
});
JS;
    }
}
