# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

_(none yet)_

---

## [1.0.0] - 2026-07-05

Initial release of **PWA Bundle** — Progressive Web App integration for Symfony.

### Added

- Dynamic **Web App Manifest** served from configuration (`ManifestBuilder`)
- Extended configuration tree covering the full W3C Web App Manifest surface: `display_override`, screenshots, related applications, scope extensions, launch handler, protocol/file handlers, share target, edge side panel, and more
- Meta tags configuration: Apple touch icons, startup images, Microsoft tile, theme variants, viewport, format detection, and extra link tags
- Configurable **service worker** (`network-first`, `cache-first`, `stale-while-revalidate`) with precache, runtime patterns, and offline fallback
- Advanced service worker options: deny cache patterns, navigation preload, cache name prefix, runtime cache max entries
- Twig helpers: `nowo_pwa_head()`, `nowo_pwa_install_prompt()`, `nowo_pwa_install_links()`, and `nowo_pwa_enabled()`
- Install prompt options: position, CSS class, delay, mobile/desktop visibility
- **Install / uninstall links** (`nowo_pwa_install_links()`) that toggle based on PWA installation state
- Route targeting (`all` / `only` / `except`) for head tags and client script
- Client configuration: register on load, check updates on visibility, reload on update
- TypeScript client (`pwa.js`) for service worker registration and install banner
- HTTP cache headers for manifest and service worker responses
- **Web Profiler data collector** (`nowo_pwa`) showing PWA status, manifest, service worker, and route targeting in the dev toolbar
- Override-friendly Twig templates and translations (REQ-TWIG-001 / REQ-I18N-001)
- Bundled translations: **en**, **es**, **fr**, **it**, **pt**, **de**, **nl** (domain `NowoPwaBundle`)
- Symfony Flex recipe `.symfony/recipes/nowo-tech/pwa-bundle/1.0.0`
- Demo Symfony 8 + FrankenPHP on port **8025**
- PHPUnit and Vitest coverage at 100% for bundle source
- Full reference in `docs/CONFIGURATION.md` and commented examples in the Flex recipe
- Documentation: INSTALLATION, CONFIGURATION, USAGE, CONTRIBUTING, CHANGELOG, UPGRADING, RELEASE, SECURITY, ENGRAM, DEMO-FRANKENPHP, SPEC-DRIVEN-DEVELOPMENT

### Fixed

- Service worker no longer intercepts or caches non-HTTP(S) requests (e.g. `chrome-extension://` from browser extensions)
- Precache URL matching uses pathname equality instead of `url.includes('/')`, which previously matched almost every request

[1.0.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.0.0
