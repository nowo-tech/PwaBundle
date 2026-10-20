# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.10.0] - 2026-10-18](#1100---2026-10-18)
- [[1.9.1] - 2026-10-13](#191---2026-10-13)
- [[1.9.0] - 2026-10-13](#190---2026-10-13)
- [[1.8.0] - 2026-10-13](#180---2026-10-13)
- [[1.7.1] - 2026-10-13](#171---2026-10-13)
- [[1.7.0] - 2026-10-12](#170---2026-10-12)
- [[1.6.1] - 2026-10-09](#161---2026-10-09)
- [[1.6.0] - 2026-10-07](#160---2026-10-07)
- [[1.5.3] - 2026-09-28](#153---2026-09-28)
- [[1.5.2] - 2026-09-27](#152---2026-09-27)
- [[1.5.1] - 2026-09-24](#151---2026-09-24)
- [[1.5.0] - 2026-08-29](#150---2026-08-29)
- [[1.4.0] - 2026-08-29](#140---2026-08-29)
- [[1.3.3] - 2026-08-24](#133---2026-08-24)
- [[1.3.2] - 2026-08-19](#132---2026-08-19)
- [[1.3.1] - 2026-08-18](#131---2026-08-18)
- [[1.3.0] - 2026-08-04](#130---2026-08-04)
- [[1.2.1] - 2026-08-03](#121---2026-08-03)
- [[1.2.0] - 2026-07-30](#120---2026-07-30)
- [[1.1.3] - 2026-07-29](#113---2026-07-29)
- [[1.1.2] - 2026-07-16](#112---2026-07-16)
- [[1.1.1] - 2026-07-16](#111---2026-07-16)
- [[1.1.0] - 2026-07-09](#110---2026-07-09)
- [[1.0.1] - 2026-07-05](#101---2026-07-05)
- [[1.0.0] - 2026-07-05](#100---2026-07-05)

## [Unreleased]

### Changed

- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19); shipped assets rebuilt.
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).
- Documentation: wave-1 REQ compliance — README standard badge line (order, Symfony 7.4 | 8.0 | 8.1+, Coverage badge), canonical FrankenPHP claim line and documentation list order, CONTRIBUTING Cursor Agent settings and post-release-commit trailer check, CODEOWNERS header and canonical bug-report template, demo `.env.example` comments, doc TOCs, Spec Kit inventory refresh (64/64).

## [1.10.0] - 2026-10-18

Install prompt and offline page on the UiKit 2.2 page contract (REQ-UI-003): theme slots, one-block `@!NowoPwaBundle` overrides (namespace now registered), self-contained offline status page; `show_never_option: false` honoured. See [UPGRADING](UPGRADING.md#to-1100).

### Added

- **UiKit page contract (REQ-UI-003)** — requires `nowo-tech/ui-kit-bundle` **^2.2**. The install prompt, install links
  and push diagnostics read the UiKit **theme slots** of `NowoPwaBundle` (`card`, `card_title`, `card_intro`, `muted`,
  `toolbar`, `btn_primary`, `btn_secondary`, `btn_link`, `btn_sm`; `nowo_ui_attrs('card' | 'toolbar')`), so a host
  themes them once in `nowo_ui_kit.theme` (or `theme.bundles.NowoPwaBundle`). Widget roots carry
  `data-nowo-ui-bundle="NowoPwaBundle"` (per-bundle tokens) and `data-testid="pwa-install-prompt"`.
- Install prompt blocks for one-block `@!` overrides: `pwa_install_backdrop`, `pwa_install_panel`, `pwa_install_text`,
  `pwa_install_message`, `pwa_install_button_install`, `pwa_install_button_remind`, `pwa_install_button_never`
  (existing: `pwa_install_mark`, `pwa_install_eyebrow`, `pwa_install_title`, `pwa_install_actions`); install links:
  `pwa_install_link`, `pwa_uninstall_link`.
- `nowo_pwa.web_ui.templates.{head,install_prompt,install_links,offline}` and `nowo_pwa.web_ui.layout_template`
  (layout of the offline page; `null` = `@NowoUiKit/layout/status.html.twig`).
- `Nowo\PwaBundle\View\OfflinePage::create()` — the offline page view model (UiKit `AuthPage`).
- `docs/TEMPLATES.md`; contract test (`PageContractTestCase`) and widget tests with a stub theme.
- Demo: UiKit document layout with a host theme (tokens + class map), a one-block install prompt override, Playwright
  e2e (`make -C demo/symfony8 test-e2e`) and README screenshots (`make -C demo/symfony8 demo-screenshots`, REQ-DEMO-013).

### Changed

- **Offline page** (`pwa/offline.html.twig`): renders on the self-contained UiKit **status layout** (inline critical CSS
  with the request CSP nonce, theme tokens, no stylesheet / script request) with the `page/auth` panel regions and
  slots; the 1.x standalone page (dark `--nowo-pwa-offline-*` palette) moved to `pwa/offline_standalone.html.twig`.
- `pwa.css`: the `--nowo-pwa-install-*` tokens fall back to the UiKit `--nowo-ui-*` tokens (surface, text, muted, border,
  radius, primary); the panel (not the root) is the painted surface; `[hidden]` always hides the prompt.
- Install prompt markup: eyebrow / title / message wrapped in `.nowo-pwa-install__text`; `aria-describedby` on the root.

### Deprecated

- `nowo_pwa.templates.*` → `nowo_pwa.web_ui.templates.*`.
- `nowo_pwa.install_prompt.button_class` / `dismiss_button_class` / `never_button_class` → UiKit theme slots
  `btn_primary` / `btn_secondary` / `btn_link` (`nowo_ui_kit.theme.bundles.NowoPwaBundle.classes.*`). Still applied
  when set.
- `pwa/offline_standalone.html.twig` (1.x offline page, opt-in).

### Fixed

- `@!NowoPwaBundle/…` is now registered (the bundle templates without the app overrides), so a host override can extend
  the bundle template and redefine one block instead of copying the file.
- `install_prompt.show_never_option: false` hides the "Don't ask again" button (the Twig `default` filter turned
  `false` into `true`).

[1.10.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.10.0

## [1.9.1] - 2026-10-13

### Fixed

- **Path route targeting:** legacy implicit `/…/` regex patterns containing `.` or `*` (e.g. `/admin.*/`) are again treated as regex (with the 1.9 deprecation) instead of silently becoming a plain path that never matched. The regex syntax list is now `^ $ \ ( ) [ ] { } | + ? *`, plus `.` when the pattern body has no inner `/` (so a plain path such as `/a.b/c/` stays a path).

[1.9.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.9.1

## [1.9.0] - 2026-10-13

### Fixed

- **Path route targeting (`match_by: path`):** a plain path written with slashes on both ends (e.g. `/blog/`) is no longer used as an unanchored regex (it matched `/x/blog/y`); it now means the path `/blog`. An invalid regex no longer raises a PHP warning on every request: it simply never matches (`preg_match()` failure is checked).

### Added

- **Explicit regex markers** for path patterns: `regex:<delimited PCRE>` (e.g. `regex:/^\/admin/`) or a `#…#` delimited pattern (e.g. `#^/admin(/|$)#i`).

### Deprecated

- Legacy implicit `/…/` regex path patterns: still treated as regex when they contain regex syntax (`^ $ \ ( ) [ ] { } | + ?`), with a deprecation (`symfony/deprecation-contracts`, now a direct requirement). Use `regex:` instead.

### Changed

- **Dev:** Vitest 4 (`vitest` / `@vitest/coverage-v8` `^4.1.11`). Tests install a placeholder `window.matchMedia` before spying (Vitest 4 only spies on existing functions) and cover the branches the new coverage engine counts; `push-checks.ts` skip-marking loop expressed with `filter()` (same behaviour; not part of the built `pwa.js`).

[1.9.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.9.0

## [1.8.0] - 2026-10-13

### Security

- Push test endpoint no longer returns raw exception messages from the push transport; failures report `send_failed` and the details are logged.
- `MinishlinkWebPushSender`: the VAPID private key is marked `#[\SensitiveParameter]` so it never appears in stack traces.

### Fixed

- Service worker offline fallback URL is generated from the offline route, so `route_prefix` is honoured.
- `service_worker.offline_url` is now used (it was ignored) and the offline page is always added to the precache list, so the navigation fallback works.
- `runtime_cache_max_entries` trims a dedicated runtime cache (`<prefix>-runtime-<version>`) instead of the shell cache, so precached pages (offline page, `/`) are no longer evicted.
- `navigation_preload: true` now consumes `event.preloadResponse` instead of issuing a second network request.
- `stale-while-revalidate` no longer produces unhandled rejections when offline and falls back to the offline page for navigations; `cache-first` also falls back for navigations.

[1.8.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.8.0

## [1.7.1] - 2026-10-13

### Changed

- Repository: `.twig-cs-fixer.cache` is now git-ignored.

### Dependencies

- `vite` 8.3.2 → 8.3.4 (frontend tooling; #51).
- `jsdom` 30.1.1 → 30.1.2 (frontend tooling; #52).

[1.7.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.7.1

## [1.7.0] - 2026-10-12

### Added

- **Web Push sender (optional, off by default)** — `nowo_pwa.web_push` (`enabled`, `vapid.public_key|private_key|subject`, `allowed_endpoint_hosts`): `Nowo\PwaBundle\WebPush\WebPushSenderInterface` aliased to `MinishlinkWebPushSender` (minishlink/web-push ^11, PSR-18/PSR-17/logger services injected when present), `WebPushEndpointGuard` (HTTPS push-service allow-list, anti-SSRF), `PushSubscriptionData`, `WebPushDeliveryReport`.
- **Fail-fast dependency checks** — `WebPushRequirements` runs at container compile time when `web_push.enabled`: missing minishlink/web-push (< 11) or PSR-17 factories break `cache:clear` with `WebPushDependencyException` ("composer require minishlink/web-push:^11.0 nyholm/psr7"); a missing PSR-18 client is reported on the first send.
- **Test notification endpoint (opt-in)** — `web_push.test_notification.*` + `routes.push_test` (`POST /pwa/push/test`, `nowo_pwa_push_test`): `PushTestController` (authenticated, `roles` default `[ROLE_USER]`, CSRF `X-CSRF-TOKEN` id `nowo_pwa_push`, `max_subscriptions` 10) sends a test push to the user's subscriptions via a host `PushSubscriptionProviderInterface` and returns per-device JSON results; expired/invalid endpoints are removed through the provider; `PushTestDeniedException` lets hosts refuse with their own error code.
- **Push diagnostics** — `src/Resources/assets/src/push-checks.ts` (`runPushChecks()`: support/secure context, VAPID config, optional preference, permission, service worker, browser subscription incl. VAPID key match + renewal, optional server copy), Stimulus peer `stimulus-peers/push_diagnostics_controller.ts` (`push-diagnostics`) and partial `@NowoPwaBundle/pwa/push_diagnostics.html.twig`; Twig function `nowo_pwa_web_push()`.
- **Browser hints** — `src/Resources/assets/src/browser-detect.ts`, Stimulus peer `stimulus-peers/browser_hint_controller.ts` (`browser-hint`), partials `@NowoPwaBundle/pwa/browser_hint.html.twig` and `browser_guide.html.twig`.
- Translations `push_diagnostics.*` and `browser_hint.*` in all 7 locales; default styles in `pwa.css`.
- Spec Kit: `specs/002-push-diagnostics/`.

### Changed

- `composer.json` **suggests** `minishlink/web-push`, `nyholm/psr7`, `symfony/http-client`, `symfony/security-bundle`, `symfony/security-csrf` (dev: added as require-dev); `php-http/discovery` plugin disabled in `allow-plugins`.
- Dev: `@hotwired/stimulus` dev dependency, `tsconfig.json` + `pnpm run typecheck`, Vitest coverage gate extended to the new TS files.
- Development: `composer.json` pins `config.platform.php` to 8.2.0 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell.

[1.7.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.7.0

## [1.6.1] - 2026-10-09

### Dependencies

- Composer refresh: Symfony 7.4.20 components, `twig/twig` 3.30.0 (lockfile); dev tooling `phpstan/phpstan` 2.3.1, `phpstan/phpstan-phpunit` 2.1.1, `phpstan/phpstan-symfony` 2.1.0, `rector/rector` 2.7.0, `igor-php/igor-php` 0.10.1.
- Demo: Symfony 8.1.8, `twig/twig` 3.30.0; regenerated `config/reference.php`.

[1.6.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.6.1

## [1.6.0] - 2026-10-07

### Added

- `Nowo\PwaBundle\Service\ManifestOverlayProviderInterface` (`overlay(array $manifest): array`): hosts can overlay brand/colors onto the web manifest without listening to `ResponseEvent`. Implementations are autoconfigured with the `nowo_pwa.manifest_overlay_provider` tag and applied by `PwaController::manifest()` after `ManifestBuilder::build()`.

## [1.5.3] - 2026-09-28

### Security

- Config validation requires `manifest.start_url` to sit under `service_worker.scope` (when scope is not `/`).

## [1.5.2] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

---

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.6.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.6.0
[1.5.3]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.5.3
[1.5.2]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.5.2

## [1.5.1] - 2026-09-24

### Fixed

- **FrankenPHP worker / `reset_kernel` false:** `PwaRouteLoader` is fully **stateless** (removed one-shot `$loaded` guard) so a long-lived worker can rebuild routes in-process without throwing.
- Explicit `kernel.reset` tag on `PwaDataCollector` for ServicesResetter when the profiler is enabled.

### Documentation

- Published / refreshed [FrankenPHP worker audit](FRANKENPHP-WORKER-AUDIT.md) (scenario A + B, 100% compatible verdict).
- Spec baseline: `FR-ROUTE-002`, `FR-HTTP-001`; code inventory synced (subscriber, SW cache defaults, web push kit script).

---

## [1.5.0] - 2026-08-29

### Added

- **`service_worker.web_push`** (default `false`): ships kit Web Push / `notificationclick` handlers into `/sw.js`
  (JSON payload: `title`, `body`, `icon`, `badge`, `url`, `tag`). Optional `web_push_defaults` for fallbacks.
  Host `append_script` still works and is appended **after** the kit handlers.

## [1.4.0] - 2026-08-29

### Added

- **`service_worker.append_script`:** optional raw JavaScript appended to the generated service
  worker (Web Push handlers, etc.) so hosts need not patch the response.
- **`http.strip_set_cookie_on_bootstrap`** (default `true`): strip `Set-Cookie` from manifest and
  service-worker responses so anonymous browser fetches cannot overwrite the session cookie.

### Notes

- Hosts that already strip Set-Cookie or append SW scripts can remove their shims after upgrading.

[1.5.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.5.1
[1.5.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.5.0
[1.4.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.4.0

## [1.3.3] - 2026-08-24

### Changed

- **QA:** add `phpstan-frankenphp` extension (REQ-CS-005).
- **Assets:** English JSDoc on TypeScript sources (REQ-ASSETS-002).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.3.3]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.3.3

## [1.3.2] - 2026-08-19

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

## [1.3.1] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.3.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.3.1

## [1.3.0] - 2026-08-04

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

---

[1.3.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.3.0

## [1.2.1] - 2026-08-03

### Fixed

- Install prompt BEM classes (`__mark`, `--banner`, …) now derive from the **first** `css_class` token so multi-class roots no longer leave the brand mark unconstrained (SVG filling the panel).
- Service worker no longer stores HTTP responses with `Cache-Control: private` or `no-store` (Symfony session/auth pages). This prevents stale login HTML and auth redirect loops when `network-first` intercepts navigations.
- Precache install now skips URLs that match `deny_cache_patterns` (builder + runtime filter).

### Changed

- Default `service_worker.deny_cache_patterns` now excludes common auth/admin/API/profiler paths (`/login`, `/logout`, `/register`, `/reset-password`, `/admin`, `/api/`, `/_profiler`, `/_wdt`, `/setup`, `/_site_backup`). An explicit empty list disables the defaults. Bump `cache_version` after upgrading so browsers drop stale SW caches.
- Dev dependencies: `nowo-tech/phpstan-frankenphp` 1.0.3, `rector/rector` 2.6.0

### Documentation

- Updated [SECURITY.md](SECURITY.md), [USAGE.md](USAGE.md), [CONFIGURATION.md](CONFIGURATION.md), and [UPGRADING.md](UPGRADING.md) for cache hardening and BEM `css_class` guidance.

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

[1.2.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.2.1
[1.2.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.2.0
[1.1.2]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.1.2
[1.1.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.1.1
[1.1.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.1.0
[1.0.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.0.0
