# Upgrading

## Table of contents

- [From 1.3.3 to 1.4.0](#from-133-to-140)
- [From 1.3.2 to 1.3.3](#from-132-to-133)

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

