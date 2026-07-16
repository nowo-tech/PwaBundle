# Upgrading

This document describes how to upgrade between versions of **PWA Bundle**.

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

[1.1.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.1.1
[1.1.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.1.0
[1.0.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.0.0
