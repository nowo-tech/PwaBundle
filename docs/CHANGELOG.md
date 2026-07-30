# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

---

## [1.2.0] - 2026-07-30

### Added

- Install prompt config options for lightweight branding: `mark_asset`, `title`, `eyebrow`, `button_class`, `dismiss_button_class`, and `never_button_class`.
- New Twig blocks for install/offline overrides: `pwa_install_mark`, `pwa_install_eyebrow`, `pwa_install_title`, `pwa_install_actions`, `pwa_offline_brand`, and `pwa_offline_content`.
- Neutral `--nowo-pwa-*` CSS custom properties for install prompt, install links, and offline branding remaps.
- `.scripts/check-open-prs.sh` and `make check-open-prs`, now included in `make release-check` for GitHub-hosted releases.

### Changed

- The default install prompt now renders structured brand/title/message sections while preserving existing helper usage.
- The offline fallback page now receives the manifest config and retry URL from the controller instead of relying on a hardcoded `/`.

### Documentation

- Updated [CONFIGURATION.md](CONFIGURATION.md), [UPGRADING.md](UPGRADING.md), and [RELEASE.md](RELEASE.md) for the new branding and release-check workflow.

---

## [1.1.3] - 2026-07-29

### Added

- FrankenPHP Friendly Worker Mode banner in README (REQ-DOCS-017).
- `make down-dev` and `make demo-smoke` (REQ-MAKE-007, REQ-TEST-011).
- PHPUnit `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` (REQ-SF-005).
- Packagist keyword `php` (REQ-PKG-004).
- **REQ-CS-005:** `nowo-tech/phpstan-frankenphp` in `require-dev` with classic + worker rulesets in `phpstan.neon.dist`.

### Changed

- Demo Symfony 8 image bumped to FrankenPHP **PHP 8.5**; demo `require.php` `>=8.5,<8.6` (REQ-DEMO-010).
- PHPStan `ignoreErrors: []` (REQ-CS-006).
- Root `.gitignore` archive patterns include `*.tgz` / `*.rar` / `*.7z` (REQ-GITIGNORE-001).
- Compose V2→V1 detection in Make (REQ-MAKE-010); optional monorepo `update-deps` include (REQ-MAKE-009).

### Documentation

- [UPGRADING.md](UPGRADING.md), [RELEASE.md](RELEASE.md) updated for 1.1.3.

---

## [1.1.2] - 2026-07-16

### Fixed

- `PwaRouteTargeting` path matching coverage: empty path, paths without leading `/`, wildcard `*`, regex patterns, and unknown mode fallback
- `make test-coverage-100` now fails correctly when coverage drops below 100% (`pipefail` with `tee`)

---

## [1.1.1] - 2026-07-16

### Added

- **REQ-GIT-001:** git hooks (`.githooks/commit-msg`), CI job, and scripts to reject Cursor `Co-authored-by` trailers in commit history
- [Code of Conduct](../CODE_OF_CONDUCT.md) (Contributor Covenant) and [GitHub Actions CI requirements](GITHUB_CI.md)
- `make setup-hooks`, `make check-no-cursor-coauthor`, and `make strip-cursor-coauthor-from-history`

### Changed

- `make release-check` now runs `check-no-cursor-coauthor` first
- Contributing and release docs document hook setup and history hygiene

---

## [1.1.0] - 2026-07-09

### Added

- **Install prompt display modes:** `banner` (fixed bar), `flash` (inline alert), `modal` (centered dialog with backdrop)
- **Per-component route targeting** for `install_prompt` and `install_links` (independent from global `route_targeting`)
- Route/path matching via `match_by: name|path` with exact paths, prefix wildcards (`/vault*`), and regex (`/^\\/admin/`)
- **Dismiss options:** remind later (`dismiss_days`) or never ask again (`never_dismiss_key`, `show_never_option`)
- Default stylesheet `pwa.css` (linked from `nowo_pwa_head()`)
- Translations: `install.dismiss_remind`, `install.dismiss_never`
- GitHub Spec Kit baseline (`specs/001-baseline/`) and operator manual [`docs/SPEC-KIT.md`](SPEC-KIT.md)

### Changed

- Global `route_targeting` now supports `match_by` (path patterns) in addition to route names
- Install prompt template uses `dismiss-remind` / `dismiss-never` actions (legacy `dismiss` still supported in JS)
- Expanded SECURITY release checklist (12.4.1)

---

## [1.0.1] - 2026-07-05

### Added

- CI jobs: **PHPStan**, **Vitest** (100% coverage thresholds), translation YAML validation
- Documentation: caching authenticated routes, CSP, trusted proxies, iOS/Safari limits, share target Symfony example, Flex recipe availability, AssetMapper note
- Codecov badge in README
- `REQ-TEST-010` traceability anchor for PHPStan in CI

### Changed

- CI Symfony matrix aligned with `composer.json` (**7.4**, 8.0, 8.1 — removed 7.0)

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

[1.1.2]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.1.2
[1.2.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.2.0
[1.1.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.1.1
[1.1.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.1.0
[1.0.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.0.0
