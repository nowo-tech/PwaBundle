# Feature Specification: PwaBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-07  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/pwa-bundle`  
**Configuration root**: `nowo_pwa`

Symfony bundle that turns any application into a configurable Progressive Web App: Web App Manifest, dynamic service worker, offline page, HTML meta tags, install prompt, and client script — with route targeting and Web Profiler diagnostics.

---

## User Scenarios & Testing

See user stories US-01…US-06 in [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md).

### User Story 1 — Serve manifest from YAML (Priority: P1)

**Given** `nowo_pwa.enabled=true` and manifest fields configured, **When** `GET /manifest.webmanifest` (or configured path), **Then** `PwaController::manifest()` returns JSON from `ManifestBuilder` with Cache-Control from `nowo_pwa.http`.

### User Story 2 — Dynamic service worker (Priority: P1)

**Given** service worker enabled, **When** `GET /sw.js`, **Then** `ServiceWorkerScriptBuilder` emits JS with precache, runtime/deny patterns, strategy, and offline URL; response includes `Service-Worker-Allowed` when configured.

### User Story 3 — Twig integration with route targeting (Priority: P1)

**Given** route targeting `only`/`except`, **When** `nowo_pwa_head()` / install helpers render, **Then** `PwaRouteTargeting` gates output per current route name or path.

### User Story 4 — Client install UX (Priority: P2)

**Given** `pwa.js` loaded with data attributes, **When** `beforeinstallprompt` fires, **Then** install banner respects dismiss TTL; install links toggle visibility based on display-mode / standalone detection.

### User Story 5 — Profiler diagnostics (Priority: P3)

**Given** `kernel.debug=true`, **When** a request renders, **Then** `PwaDataCollector` exposes manifest/SW/client config in the `nowo_pwa` profiler panel.

---

## Requirements

### Bundle & DI

- **FR-BUNDLE-001**: `PwaBundle` MUST register `TwigPathsPass` and alias `nowo_pwa`.
- **FR-CFG-001**: Root config MUST define `enabled`, `route_prefix`, manifest/meta/service_worker/install_prompt/install_links/client/http/routes/templates nodes.
- **FR-CFG-002**: `PwaExtension` MUST load services, publish `%nowo_pwa.*%` parameters, prepend asset package `nowo_pwa`, and load `data_collector.yaml` in debug.
- **FR-CFG-003…006**: Sub-node definitions MUST match [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md) keys.
- **FR-DI-001 / FR-DI-002 / FR-DI-003**: Service wiring, collector tag, Twig path registration as listed in inventory.

### Routing & HTTP

- **FR-ROUTE-001**: `PwaRouteLoader` (type `nowo_pwa`) MUST register manifest, service worker, and offline routes with configurable prefix.
- **FR-CTRL-001**: Manifest action MUST 404 when disabled; return `application/manifest+json`.
- **FR-CTRL-002**: Service worker action MUST return `application/javascript` with generated script.
- **FR-CTRL-003**: Offline action MUST render `@NowoPwaBundle/pwa/offline.html.twig`.

### Services

- **FR-SVC-001**: `ManifestBuilder` MUST normalize icons, shortcuts, share_target, and omit empty optional W3C fields.
- **FR-SVC-002**: `ServiceWorkerScriptBuilder` MUST support `network-first`, `cache-first`, `stale-while-revalidate`, precache list, deny/runtime patterns.
- **FR-SVC-002b**: The generated service worker MUST NOT store responses with `Cache-Control: private` or `no-store`, MUST filter `precache_urls` against deny patterns, and MUST ship safe default deny patterns for auth/admin/API/profiler paths.
- **FR-SVC-003**: `PwaRouteTargeting` MUST support modes `all`, `only`, `except` with name/path matching (prefix `*`, regex `/…/`).

### Twig

- **FR-TWIG-001**: App overrides MUST win via `TwigPathsPass` prepend + bundle namespace `NowoPwaBundle`.
- **FR-TWIG-002**: Extension MUST expose `nowo_pwa_enabled()`, `nowo_pwa_head()`, `nowo_pwa_install_prompt()`, `nowo_pwa_install_links()`.
- **FR-TWIG-003…006**: Templates MUST match documented markup and translation keys.

### Frontend

- **FR-ASSET-001 / FR-ASSET-002**: TypeScript entry and library MUST register SW, handle updates, install prompt/links, dismiss storage.
- **FR-ASSET-003 / FR-ASSET-004**: Published `pwa.js` / `pwa.css` MUST ship via asset package; build documented in README/Makefile.
- **FR-TEST-001**: `pwa-client.test.ts` MUST maintain Vitest coverage contract (see `REQ-TEST-009` in SDD).
- **FR-I18N-001**: Seven locale files MUST share keys `install.*` and `offline.*` under domain `NowoPwaBundle`.

### Web Profiler

- **FR-PROF-001…003**: Collector, panel template, and SVG icon as mapped in inventory.

---

## Success Criteria

- **SC-001**: **35/35** files mapped in [`code-inventory.md`](code-inventory.md).
- **SC-002**: Config keys in [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md) match `Configuration.php` and node definitions.
- **SC-003**: `make release-check` passes (PHPUnit 100%, Vitest 100%, PHPStan).
- **SC-004**: Demo healthcheck returns valid manifest JSON and SW with cache prefix.

---

## Out of scope

- Push notifications, background sync queues, native app wrappers (see SDD non-goals).
