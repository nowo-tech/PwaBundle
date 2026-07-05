# Usage

## Layout integration

Add to your base Twig layout (e.g. `templates/base.html.twig`):

```twig
<!DOCTYPE html>
<html lang="{{ app.request.locale|default('en') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}App{% endblock %}</title>
    {{ nowo_pwa_head() }}
    {% block stylesheets %}{% endblock %}
</head>
<body>
    {% block body %}{% endblock %}
    {{ nowo_pwa_install_prompt() }}
    {{ nowo_pwa_install_links() }}
    {% block javascripts %}{% endblock %}
</body>
</html>
```

`nowo_pwa_head()` is a no-op when PWA is disabled or the current route is excluded via `route_targeting`.

## Icons

Place PNG icons in `public/icons/` (or your asset pipeline) and reference them in config:

```yaml
nowo_pwa:
    manifest:
        icons:
            - { src: '/icons/icon-192.png', sizes: '192x192', type: image/png }
            - { src: '/icons/icon-512.png', sizes: '512x512', type: image/png, purpose: 'any maskable' }
```

## Route targeting

Limit PWA injection to specific pages:

```yaml
nowo_pwa:
    route_targeting:
        mode: except
        routes:
            - admin_dashboard
            - api_docs
```

Modes: `all` (default), `only`, `except`.

## Service worker strategies

| Strategy | Behaviour |
|----------|-----------|
| `network-first` | Try network; fall back to cache / offline page on failure |
| `cache-first` | Serve cache when available |
| `stale-while-revalidate` | Return cache immediately, refresh in background |

Bump `service_worker.cache_version` when you change precache lists to invalidate old caches.

## Overrides

| Resource | Override path |
|----------|---------------|
| Head partial | `templates/bundles/NowoPwaBundle/pwa/head.html.twig` |
| Install banner | `templates/bundles/NowoPwaBundle/pwa/install_prompt.html.twig` |
| Install / uninstall links | `templates/bundles/NowoPwaBundle/pwa/install_links.html.twig` |
| Offline page | `templates/bundles/NowoPwaBundle/pwa/offline.html.twig` |
| Translations | `translations/NowoPwaBundle.{locale}.yaml` |

Bundled locales: **en**, **es**, **fr**, **it**, **pt**, **de**, **nl** (domain `NowoPwaBundle`). Symfony uses the request locale; override or add languages from your app as shown above.

## Assets

After installing the bundle in your app:

```bash
php bin/console assets:install
```

The client script is published as `/bundles/pwa/pwa.js` (package `nowo_pwa`).

## Web Profiler (dev)

When `APP_DEBUG=1` and `symfony/web-profiler-bundle` is installed, a **PWA** toolbar item shows:

- Whether the bundle is active on the current route (respecting `route_targeting`)
- Manifest summary (name, display, lang, URLs)
- Service worker strategy, cache version, and install UI flags

Click the toolbar entry or open the **PWA** panel in the profiler for full details.
