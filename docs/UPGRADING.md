# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.11.0](#to-1110)
- [To 1.10.2](#to-1102)
- [To 1.10.1](#to-1101)
- [To 1.10.0](#to-1100)
  - [Template contract (1.10)](#template-contract-110)
- [To 1.9.1](#to-191)
- [To 1.9.0](#to-190)
- [To 1.8.0](#to-180)
- [To 1.7.1](#to-171)
- [To 1.7.0](#to-170)
- [To 1.6.1](#to-161)
- [To 1.6.0](#to-160)
- [To 1.5.3](#to-153)
- [To 1.5.2](#to-152)
- [From 1.5.0 to 1.5.1](#from-150-to-151)
  - [Notes](#notes)
- [From 1.4.0 to 1.5.0](#from-140-to-150)
  - [Notes](#notes-1)
- [From 1.3.3 to 1.4.0](#from-133-to-140)
  - [Notes](#notes-2)
- [From 1.3.2 to 1.3.3](#from-132-to-133)
- [1.2.1 (2026-08-03)](#121-2026-08-03)
  - [Upgrade steps](#upgrade-steps)
  - [Default deny patterns](#default-deny-patterns)
- [1.2.0 (2026-07-30)](#120-2026-07-30)
  - [Upgrade steps](#upgrade-steps-1)
  - [New install prompt options (optional)](#new-install-prompt-options-optional)
  - [New Twig blocks](#new-twig-blocks)
- [1.1.3 (2026-07-29)](#113-2026-07-29)
- [1.1.2 (2026-07-16)](#112-2026-07-16)
- [1.1.1 (2026-07-16)](#111-2026-07-16)
- [1.1.0 (2026-07-09)](#110-2026-07-09)
  - [Upgrade steps](#upgrade-steps-2)
  - [New options (optional)](#new-options-optional)
  - [Custom install prompt template](#custom-install-prompt-template)
- [1.0.1 (2026-07-05)](#101-2026-07-05)
  - [Recommended hardening (optional)](#recommended-hardening-optional)
- [1.0.0 (2026-07-05)](#100-2026-07-05)
  - [Fresh install](#fresh-install)
  - [Service worker cache busting](#service-worker-cache-busting)
- [To 1.3.2](#to-132)
- [To 1.3.1](#to-131)
- [To 1.3.0](#to-130)
  - [Twig Extra Bundle (REQ-TWIG-004)](#twig-extra-bundle-req-twig-004)
  - [Twig-CS-Fixer (maintainers)](#twig-cs-fixer-maintainers)

## Unreleased

Web Push deliveries now run with bundle deadlines (REQ-RUNTIME-001): `web_push.connect_timeout: 3.0` and
`web_push.timeout: 5.0` seconds. If your app relies on the FrameworkBundle `Psr\Http\Client\ClientInterface`
(Symfony `Psr18Client`), these values now **override** `framework.http_client.default_options.timeout` /
`max_duration` for push requests only — set the new keys if a push service legitimately needs longer. Non-Symfony
PSR-18 clients (Guzzle…) are unaffected and keep their own timeouts. `connect_timeout` greater than `timeout` fails
the container build.

## To 1.11.0

From **1.10.2**: `composer update nowo-tech/pwa-bundle`. Optional: if you override `pwa/install_prompt.html.twig` only to
redefine `pwa_install_mark`, set `nowo_pwa.install_prompt.mark_template` to your mark template and delete the override.

## To 1.10.2

From **1.10.1**:

```bash
composer update nowo-tech/pwa-bundle
```

## To 1.10.1

From **1.10.0**:

```bash
composer update nowo-tech/pwa-bundle
```

## To 1.10.0

From **1.9.1**:

```bash
composer update nowo-tech/pwa-bundle
```

### Template contract (1.10)

PwaBundle follows the **UiKit 2.x page contract** (REQ-UI-003) and requires **`nowo-tech/ui-kit-bundle` ^2.2**
(`composer update nowo-tech/pwa-bundle -W`; register `Nowo\UiKitBundle\NowoUiKitBundle` in `config/bundles.php` if
Flex did not). Hosts that only configured `nowo_pwa` need no template work; check the points below.

**Widgets and page** ([TEMPLATES.md](TEMPLATES.md))

| Template | Contract | Blocks |
| --- | --- | --- |
| `pwa/install_prompt.html.twig` | widget, theme slots `card`, `card_title`, `card_intro`, `muted`, `toolbar`, `btn_primary`, `btn_secondary`, `btn_link` | `pwa_install_backdrop`, `pwa_install_panel`, `pwa_install_mark`, `pwa_install_text`, `pwa_install_eyebrow`, `pwa_install_title`, `pwa_install_message`, `pwa_install_actions`, `pwa_install_button_install`, `pwa_install_button_remind`, `pwa_install_button_never` |
| `pwa/install_links.html.twig` | widget, slots `btn_primary` / `btn_secondary` + `btn_sm` | `pwa_install_link`, `pwa_uninstall_link` |
| `pwa/push_diagnostics.html.twig` | widget, slots `toolbar`, `btn_primary`, `btn_secondary` | unchanged |
| `pwa/offline.html.twig` | unique page on `@NowoUiKit/layout/status.html.twig`, view model `page` (`AuthPage`) | `pwa_offline_head`, `pwa_offline_brand`, `pwa_offline_content`, `pwa_offline_actions` |

**What hosts may notice**

1. **Install prompt colours.** `pwa.css` now reads the UiKit tokens first: with `nowo-ui.css` (or
   `nowo_ui_kit.theme.tokens`) on the page the prompt follows the host surface / text / primary colours instead of the
   1.x dark navy panel. To keep the 1.x look, set the bundle tokens in your CSS:
   `:root { --nowo-pwa-install-bg: #0f172a; --nowo-pwa-install-color: #f8fafc; --nowo-pwa-install-message-color: rgba(248, 250, 252, .92); --nowo-pwa-install-eyebrow-color: #cbd5e1; --nowo-pwa-install-secondary-color: #cbd5e1; --nowo-pwa-install-primary-bg: #2563eb; --nowo-pwa-install-primary-color: #fff; }`.
   The panel (`.nowo-pwa-install__panel`), not the root, is the painted surface; CSS that painted
   `.nowo-pwa-install` itself should target the panel (or the tokens).
2. **Install prompt markup.** Buttons, panel, title, message and actions also carry the slot classes (`nowo-ui-btn
   nowo-ui-btn--primary` + your `theme.classes.btn_primary`, `nowo-ui-card` …; with `css_framework: bootstrap5` also
   `btn btn-primary`, `card`, …). Eyebrow / title / message sit in a new `.nowo-pwa-install__text` wrapper. The ids,
   `data-pwa-install-action` hooks and BEM classes are unchanged.
3. **Offline page.** `/offline` renders on the self-contained UiKit status layout (inline CSS with the request
   `csp_nonce`, theme tokens, light / dark from the host tokens) instead of the 1.x dark standalone page; the
   `--nowo-pwa-offline-*` custom properties no longer apply. The blocks `pwa_offline_brand` and `pwa_offline_content`
   keep their names (inside `nowo_ui_content`). Want the 1.x page back for one minor version:
   `nowo_pwa.web_ui.templates.offline: '@NowoPwaBundle/pwa/offline_standalone.html.twig'` (deprecated).
4. **Template context.** The offline template receives `page` (a UiKit `AuthPage`) in addition to the 1.x
   `manifest_name`, `manifest` and `retry_url`.
5. **`show_never_option: false`** now really hides the "Don't ask again" button.

**Migrating overrides.** `@!NowoPwaBundle/…` is now registered, so a fork under
`templates/bundles/NowoPwaBundle/pwa/install_prompt.html.twig` can shrink to:

```twig
{% extends '@!NowoPwaBundle/pwa/install_prompt.html.twig' %}

{% block pwa_install_mark %}
    <div class="{{ bem }}__brand"><span class="{{ bem }}__mark" aria-hidden="true">{% include '_brand_icon.html.twig' %}</span></div>
{% endblock %}
```

(or live outside `templates/bundles/` and be wired with `nowo_pwa.web_ui.templates.install_prompt`).

**Deprecated configuration** (still read in 1.x, removed in 2.0)

| Key | Replacement |
| --- | --- |
| `templates.head` / `install_prompt` / `install_links` / `offline` | `web_ui.templates.*` (copied there when the canonical key is not set) |
| `install_prompt.button_class` | `nowo_ui_kit.theme.bundles.NowoPwaBundle.classes.btn_primary` (still replaces the slot classes when set) |
| `install_prompt.dismiss_button_class` | `nowo_ui_kit.theme.bundles.NowoPwaBundle.classes.btn_secondary` |
| `install_prompt.never_button_class` | `nowo_ui_kit.theme.bundles.NowoPwaBundle.classes.btn_link` |

A theme exception that should drop the UiKit framework defaults of a slot (e.g. Bootstrap `btn btn-primary`) uses
the long form: `btn_primary: { value: 'app-install', framework_defaults: false }`.

## To 1.9.1

From **1.9.0** — legacy /…/ targeting regexes with . or * keep regex behaviour

```bash
composer update nowo-tech/pwa-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.9.0

From **1.8.0** — explicit regex markers for route targeting patterns; vitest 4.

```bash
composer update nowo-tech/pwa-bundle
```

- **Path route targeting:** write regex path patterns with an explicit marker: `'regex:/^\/admin/'` or `'#^/admin(/|$)#'` (single-quoted in YAML). Legacy `/…/` regexes keep working when they contain regex syntax (`^ $ \ ( ) [ ] { } | + ?`) but trigger a deprecation. A `/…/` pattern without such syntax (e.g. `/blog/`) is now the plain path `/blog` instead of a regex matching `blog` anywhere in the URL; if you relied on that substring match, use `'regex:#/blog(/|$)#'`.
- Invalid regex patterns no longer warn; they never match. Check your targeting if a page unexpectedly loses (or keeps) the PWA assets.

## To 1.8.0

From **1.7.1** — service worker - offline page precached, route_prefix honoured, separate runtime cache, navigation preload used.

```bash
composer update nowo-tech/pwa-bundle
```

- Runtime responses now live in a separate `<prefix>-runtime-<version>` cache; precached pages are never trimmed. Old caches are cleaned on activate.
- The offline page is always precached; its URL honours `route_prefix`, or `service_worker.offline_url` when set (that option now works).
- The push test endpoint returns `reason: "send_failed"` instead of raw exception text.

## To 1.7.1

From **1.7.0** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/pwa-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.7.0

From **1.6.1** — optional Web Push sender, push diagnostics, test notification endpoint and browser hints. **No breaking changes**: everything is off by default, no config change needed.

```bash
composer update nowo-tech/pwa-bundle
php bin/console cache:clear
```

To adopt (optional):

1. Sender: `composer require minishlink/web-push:^11.0 nyholm/psr7` (plus `symfony/http-client` if no PSR-18 client), then `nowo_pwa.web_push.enabled: true` with `vapid.public_key` / `private_key` / `subject`. Missing PSR-17 factories now fail `cache:clear` instead of failing every delivery at runtime.
2. Test endpoint: implement `Nowo\PwaBundle\WebPush\PushSubscriptionProviderInterface`, set `web_push.test_notification.enabled: true` (adjust `roles`, `csrf_token_id`, `routes.push_test`). Requires `symfony/security-bundle` + `symfony/security-csrf`.
3. Frontend: register the Stimulus peers `push-diagnostics` / `browser-hint` from `vendor/nowo-tech/pwa-bundle/src/Resources/assets/stimulus-peers/` (host needs `@hotwired/stimulus`) and include `@NowoPwaBundle/pwa/push_diagnostics.html.twig` / `browser_hint.html.twig` / `browser_guide.html.twig`. See [USAGE](USAGE.md#push-diagnostics-test-notification-and-browser-hints).
4. Templates that override `@NowoPwaBundle/pwa/*` are unaffected; the `routes` parameter now also contains `push_test`.

## To 1.6.1

From **1.6.0** — dependency refresh only.

```bash
composer update nowo-tech/pwa-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.6.0

From **1.5.3** — new `ManifestOverlayProviderInterface`. No breaking changes.

```bash
composer update nowo-tech/pwa-bundle
php bin/console cache:clear
```

- Optional: replace `ResponseEvent` listeners that rewrite `/manifest.webmanifest` with a service implementing `Nowo\PwaBundle\Service\ManifestOverlayProviderInterface` (`overlay(array $manifest): array`). It is autoconfigured with the `nowo_pwa.manifest_overlay_provider` tag (use that tag manually if autoconfigure is disabled).

## To 1.5.3

From **1.5.2** — `start_url` must be under SW scope.

```bash
composer update nowo-tech/pwa-bundle
php bin/console cache:clear
```

- If `service_worker.scope` is not `/`, ensure `manifest.start_url` is under that scope or config compile will fail.

## To 1.5.2

From **1.5.1** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/pwa-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## From 1.5.0 to 1.5.1

FrankenPHP worker hardening. **No migrations** and no required config changes.

```bash
composer update nowo-tech/pwa-bundle
php bin/console cache:clear
```

### Notes

1. Safe under FrankenPHP workers that keep the same kernel between requests (`reset_kernel` / reboot disabled) — see [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).
2. `PwaRouteLoader` no longer throws if `load()` is invoked more than once in the same process (stateless collections).

## From 1.4.0 to 1.5.0

Additive kit Web Push SW handlers. **No migrations.**

```bash
composer update nowo-tech/pwa-bundle
php bin/console cache:clear
```

### Notes

1. Prefer `service_worker.web_push: true` (+ optional `web_push_defaults`) over a host-maintained `append_script` file for standard push / `notificationclick` UI.
2. Compose notification `title` / `body` / `url` in the push JSON on the server; bump `cache_version` after enabling.
3. Hosts that already ship a full push append script can delete it and switch to `web_push: true`, or keep `append_script` for extras only (runs after the kit script).

## From 1.3.3 to 1.4.0

Additive service-worker / HTTP bootstrap options. **No required application changes.**

```bash
composer update nowo-tech/pwa-bundle
php bin/console cache:clear
```

### Notes

1. Optional `service_worker.append_script` for Web Push (or other) handlers — prefer this over response subscribers that rewrite `/sw.js`.
2. `http.strip_set_cookie_on_bootstrap` defaults to `true` (strips `Set-Cookie` on manifest + SW). Set to `false` only if you intentionally need cookies on those endpoints.
3. Host shims that append SW scripts or strip bootstrap cookies can be removed after upgrading.

## From 1.3.2 to 1.3.3

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/pwa-bundle
```

# Upgrading

This document describes how to upgrade between versions of **PWA Bundle**.

## 1.2.1 (2026-08-03)

Security hardening for service worker caching (plus install-prompt BEM fix). **Behaviour change** for apps that relied on caching Symfony `Cache-Control: private` HTML — that is no longer stored at runtime (correct for session pages). Offline still works via `precache_urls` and `offline_url`.

### Upgrade steps

```bash
composer update nowo-tech/pwa-bundle
php bin/console cache:clear
```

Bump `service_worker.cache_version` so browsers activate the new SW and drop old caches:

```yaml
nowo_pwa:
    service_worker:
        cache_version: v2   # or any new value
```

### Default deny patterns

If you omit `deny_cache_patterns`, the bundle now denies `/login`, `/logout`, `/register`, `/reset-password`, `/admin`, `/api/`, `/_profiler`, `/_wdt`, `/setup`, and `/_site_backup`.

- To **extend** defaults, list the defaults plus your paths (config replaces, it does not merge).
- To **disable** defaults entirely: `deny_cache_patterns: []`.

Never put `/login` (or locale variants) in `precache_urls` — denied URLs are filtered out, but precaching auth HTML remains a footgun.

See [SECURITY.md — Caching authenticated routes](SECURITY.md#caching-authenticated-routes).

---

## 1.2.0 (2026-07-30)

Minor release: install/offline templates are easier to brand without full Twig forks. **No breaking changes** — existing helper calls keep working.

### Upgrade steps

```bash
composer update nowo-tech/pwa-bundle
php bin/console assets:install
php bin/console cache:clear
```

Republish bundle assets so the updated `pwa.css` with `--nowo-pwa-*` custom properties is available in `public/bundles/pwa/pwa.css`.

### New install prompt options (optional)

```yaml
nowo_pwa:
    install_prompt:
        mark_asset: '/icons/brand-mark.svg'
        eyebrow: 'Available offline'
        title: 'Install Beacon'
        button_class: ''
        dismiss_button_class: null
        never_button_class: null
```

- `button_class: ''` keeps the bundle-generated BEM classes based on `css_class`.
- `dismiss_button_class: null` and `never_button_class: null` also derive BEM classes automatically.
- `title` and `eyebrow` can be plain text or translation keys handled by your app translator.

### New Twig blocks

Install prompt:

- `pwa_install_mark`
- `pwa_install_eyebrow`
- `pwa_install_title`
- `pwa_install_actions`

Offline page:

- `pwa_offline_brand`
- `pwa_offline_content`

If you previously overrode the full `install_prompt.html.twig` or `offline.html.twig` just to change branding or button classes, consider deleting those forks and remapping config/CSS tokens instead.

---

## 1.1.3 (2026-07-29)

Patch release: FrankenPHP banner, Make/demo polish, PHPStan FrankenPHP rulesets for contributors, and Packagist keyword. **No breaking changes** and no required config changes in consuming applications.

```bash
composer update nowo-tech/pwa-bundle
```

Contributors: run `composer install` so `vendor/nowo-tech/phpstan-frankenphp` is available for `make phpstan`.

---

## 1.1.2 (2026-07-16)

Patch release: restore 100% PHP coverage for path-based route targeting and make `make test-coverage-100` fail when coverage drops. **No breaking changes** and no required config changes in consuming applications.

```bash
composer update nowo-tech/pwa-bundle
```

---

## 1.1.1 (2026-07-16)

Patch release: maintainer tooling, Code of Conduct, and CI git-hygiene (REQ-GIT-001). **No breaking changes** and no required config or code changes in consuming applications.

```bash
composer update nowo-tech/pwa-bundle
```

Contributors cloning this repository should run `make setup-hooks` once (see [CONTRIBUTING.md](CONTRIBUTING.md)).

---

## 1.1.0 (2026-07-09)

Minor release: install UI enhancements, path-based route targeting, and bundled default styles. **No breaking changes** — existing configs and Twig overrides keep working.

### Upgrade steps

```bash
composer update nowo-tech/pwa-bundle
php bin/console assets:install
php bin/console cache:clear
```

Run `assets:install` so the new `pwa.css` is published to `public/bundles/pwa/pwa.css`. It is linked automatically from `nowo_pwa_head()`.

### New options (optional)

**Install prompt display modes** — default remains `banner`:

```yaml
nowo_pwa:
    install_prompt:
        display: modal    # banner | flash | modal
        show_never_option: true
        never_dismiss_key: nowo_pwa_install_never
```

For `display: flash`, place `{{ nowo_pwa_install_prompt() }}` inline in your page content instead of only in the layout footer.

**Path-based route targeting** — match by URL path instead of Symfony route name:

```yaml
nowo_pwa:
    route_targeting:
        match_by: path
        mode: except
        routes:
            - '/admin*'
            - '/api/private'
```

Per-component targeting is also available under `install_prompt.route_targeting` and `install_links.route_targeting`.

### Custom install prompt template

If you override `install_prompt.html.twig`, update dismiss buttons to `data-pwa-install-action="dismiss-remind"` and `dismiss-never`. The legacy `dismiss` action still works in `pwa.js`.

See [CONFIGURATION.md — Install prompt](CONFIGURATION.md#install-prompt) and [USAGE.md](USAGE.md).

---

## 1.0.1 (2026-07-05)

Patch release: documentation and CI improvements only. **No breaking changes** and no required config or code changes in consuming applications.

### Recommended hardening (optional)

Review the new security guidance and tighten service worker caching if your app serves authenticated pages:

```yaml
nowo_pwa:
    service_worker:
        deny_cache_patterns:
            - '/admin'
            - '/api/private'
            - '/_profiler'
            - '/_wdt'
```

See [SECURITY.md — Caching authenticated routes](SECURITY.md#caching-authenticated-routes) and [USAGE.md — Browser and platform notes](USAGE.md#browser-and-platform-notes).

---

## 1.0.0 (2026-07-05)

Initial release. There are no prior versions of `nowo-tech/pwa-bundle`.

### Fresh install

```bash
composer require nowo-tech/pwa-bundle
php bin/console assets:install
php bin/console cache:clear
```

Configure `config/packages/nowo_pwa.yaml` and add Twig helpers as described in [INSTALLATION.md](INSTALLATION.md).

### Service worker cache busting

When you change precache URLs or caching strategy in a deployed app, increment:

```yaml
nowo_pwa:
    service_worker:
        cache_version: v2
```

Browsers will drop old `nowo-pwa-*` caches on the next activation.

[1.1.2]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.1.2
[1.1.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.1.1
[1.1.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.1.0
[1.0.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.0.0

## To 1.3.2

No application upgrade steps.

```bash
composer update nowo-tech/pwa-bundle
```

## To 1.3.1

No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

```bash
composer update nowo-tech/pwa-bundle
php bin/console cache:clear
```

## To 1.3.0

From **1.2.1** — Adds required Twig Extra (REQ-TWIG-004) and Twig-CS-Fixer. Register TwigExtraBundle if Flex did not.

```bash
composer update nowo-tech/pwa-bundle
php bin/console cache:clear
```

### Twig Extra Bundle (REQ-TWIG-004)

Hosts that render this bundle's Twig templates must install:

```bash
composer require twig/extra-bundle twig/string-extra
```

and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle`. Flex recipes usually register it automatically.

### Twig-CS-Fixer (maintainers)

Package maintainers: `composer twig:lint` / `composer twig:fix` use `.twig-cs-fixer.php` over `src/` (and `templates/` when present).

