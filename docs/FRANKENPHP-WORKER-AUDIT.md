# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/pwa-bundle` (`symfony-bundle`) |
| Audited revision | `v1.5.1` (post-hardening); push test endpoint re-checked for 1.16.0 |
| Audit date | 2026-09-24 (push test endpoint and worker test: 2026-11-11) |
| Method | Manual review of every PHP file under `src/` (controller, Twig extension, subscriber, route loader, data collector, services, DI extension, compiler pass, config) + PHPStan FrankenPHP classic/worker rulesets |
| **Verdict** | ✅ **100% compatible** with FrankenPHP worker mode when the kernel is **not** rebooted between requests (`reset_kernel` / reboot disabled). Runtime services hold only compiled config; request data is read lazily per call and never stored on shared instances. |

## Table of contents

- [Execution model assumed](#execution-model-assumed)
- [Summary](#summary)
- [Services reviewed](#services-reviewed)
- [Findings](#findings)
  - [W-01 — Profiler data collector (Info, debug only)](#w-01--profiler-data-collector-info-debug-only)
  - [W-02 — Absolute URLs computed per request (Info)](#w-02--absolute-urls-computed-per-request-info)
- [Worker test (kernel reset false)](#worker-test-kernel-reset-false)
- [Hardening in v1.5.1](#hardening-in-v151)
- [Usage recommendations in worker mode](#usage-recommendations-in-worker-mode)
- [Re-audit triggers](#re-audit-triggers)

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests. This is the usual FrankenPHP + Symfony Runtime setup when `reset_kernel` is false.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service would leak into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM. **PwaBundle targets B.**

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | Controller, Twig extension, subscriber and builders are `readonly` / property-free; `PwaRouteLoader` is fully stateless (fresh `RouteCollection` every `load()`) |
| Static properties / `static` locals | ✅ | None; only static config helpers (`*NodeDefinition::configure()`, `ServiceWorkerCacheDefaults::denyCachePatterns()`) |
| `ResetInterface` / `kernel.reset` coverage | ✅ | `PwaDataCollector` tagged `kernel.reset` → `reset()` (debug only); no other shared service needs a reset |
| Request / user / locale captured in services | ✅ | `PwaTwigExtension` reads `RequestStack::getCurrentRequest()` on each call; controller uses the injected `Request` argument |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used |
| Doctrine / EntityManager | ✅ | The bundle has no persistence of its own; push subscriptions are read and removed through the host's port (`PushRecipientSubscriptionProviderInterface`). The bundle never clears, flushes or resets the host's default EntityManager (rule 7); the test host keeps its storage on a dedicated entity manager |
| Output, headers, `exit`, shutdown functions | ✅ | Headers and cookies are set on `Response` objects only (`PwaBootstrapStatelessCookieSubscriber` strips `Set-Cookie` on bootstrap paths) |
| Resources (files, sockets, cURL) held open | ✅ | Only `file_get_contents()` of the Web Push SW snippet at container build time (`PwaExtension`) |
| Memory growth across requests | ✅ | No caches or accumulating arrays |
| Blocking I/O and timeouts | ✅ | Only optional Web Push deliveries (outbound HTTP), bounded by `web_push.connect_timeout` / `timeout` through `WebPushHttpClientFactory` (REQ-RUNTIME-001); the client is built per send, nothing is kept between requests |
| Third-party static state | ✅ | Only Symfony / Twig |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` in `phpstan.neon.dist` |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `PwaController` (public) | yes | none (`readonly` config and builders) | ✅ | ✅ |
| `PwaTwigExtension` | yes | none (`readonly`; current request fetched per call) | ✅ | ✅ |
| `PwaBootstrapStatelessCookieSubscriber` | yes | none (`readonly` flag and paths) | ✅ | ✅ |
| `ManifestBuilder` | yes | none (no properties) | ✅ | ✅ |
| `ServiceWorkerScriptBuilder` | yes | none (no properties) | ✅ | ✅ |
| `PwaRouteTargeting` | yes | none (no properties) | ✅ | ✅ |
| `PwaRouteLoader` (`routing.loader`) | yes | none (stateless `load()`) | ✅ | ✅ |
| `PwaDataCollector` (`data_collector`, only when `kernel.debug`) | yes | `$data`, fully overwritten in `collect()`, cleared via `kernel.reset` | ✅ | ✅ (debug only; see W-01) |
| `PushTestController` (only with `web_push.test_notification.enabled`) | yes | none (`readonly`; the user and identifier are read from the token storage per request) | ✅ | ✅ |
| `LegacyPushSubscriptionProviderAdapter` (1.16, only when the host implements the deprecated port) | yes | none (`readonly`; reads the token on every call and answers only for that user) | ✅ | ✅ |
| `MinishlinkWebPushSender` / `WebPushEndpointGuard` / `WebPushHttpClientFactory` | yes | none (config only; the HTTP client is built per send) | ✅ | ✅ |

## Findings

No findings above Info.

### W-01 — Profiler data collector (Info, debug only)

- **Where:** `PwaDataCollector` — `collect()` assigns `$this->data`; `reset()` clears it; tagged `kernel.reset`.
- **Worker impact:** registered only when `kernel.debug` is true. Under scenario A the profiler / services resetter clears it. Under scenario B the previous request’s route summary stays until the next `collect()` overwrites the array (config + route name, not user secrets).
- **Recommendation:** do not enable the Web Profiler in production workers.

### W-02 — Absolute URLs computed per request (Info)

- **Where:** `PwaController::manifest()` / `serviceWorker()` use `$request->getSchemeAndHttpHost()` each time.
- **Worker impact:** never cached on the service; multi-host deployments do not leak hosts across requests.
- **Recommendation:** configure Symfony `trusted_proxies` / `trusted_hosts` as usual (manifest may be publicly cacheable).

## Worker test (kernel reset false)

`tests/Integration/DddHostPushTest::testKeptAliveWorkerWithoutServicesResetterNeverMixesRecipients` boots **one**
kernel (`DddPushTestKernel`) whose `services_resetter` service is removed, so nothing resets services between
requests, and drives it with a `KernelBrowser` that never reboots. It sends **1,500** `POST /pwa/push/test` requests
alternating **alice, bob and a guest** (500 each) and asserts on every request that only that user's subscriptions
are pushed (the guest gets 401 and nothing is sent, so no previous user's token leaks). Memory, measured after 30
warm-up requests, must not grow by more than **2 MB**. The host storage lives on a dedicated entity manager that the
host adapter clears after each read.

`PushRecipientPortEquivalenceTest::testAlternatingUsersOnOneControllerNeverMixRecipients` repeats the alternation
(200 requests) on one controller instance with the deprecated port (through the adapter) and the new port and
requires identical results.

Result: nothing is carried between requests, so the bundle is **safe with kernel reset false**. Rule 7: the bundle never clears, flushes or resets the host's default EntityManager.

Igor (`composer igor`): 0 KO. PHPStan with the FrankenPHP classic and worker rulesets: no errors.

## Hardening in v1.5.1

| Change | Why |
|--------|-----|
| Removed `PwaRouteLoader::$loaded` guard | Previous one-shot flag could throw on a second `load()` in a long-lived worker if the router was rebuilt without process restart |
| Explicit `kernel.reset` on `PwaDataCollector` | Makes reset contract visible for FrankenPHP / ServicesResetter |

## Usage recommendations in worker mode

- No special PWA config is required for `reset_kernel: false`.
- Keep `http.strip_set_cookie_on_bootstrap` enabled (default) so manifest / SW responses never rotate the session cookie.
- Do not store request data in Twig globals or custom extension properties when calling `nowo_pwa_*`.
- Demo: `demo/symfony8/docker/frankenphp/Caddyfile` runs FrankenPHP `worker { file …; watch }`.

## Re-audit triggers

Re-run this audit when a change adds: instance properties to the controller, Twig extension, subscriber or builders; a manifest / SW script cache on a shared service; runtime file reads; a new dependency on the security token or session (the push test endpoint reads the token per request since 1.7); or any use of `$_SERVER` / `$_ENV` at request time.
