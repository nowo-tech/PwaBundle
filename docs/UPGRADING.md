# Upgrading

This document describes how to upgrade between versions of **PWA Bundle**.

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

[1.0.1]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/PwaBundle/releases/tag/v1.0.0
