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

### Exclude authenticated routes from cache

The service worker can cache navigation responses that include session-specific HTML. Exclude sensitive paths:

```yaml
nowo_pwa:
    service_worker:
        deny_cache_patterns:
            - '/admin'
            - '/api/private'
            - '/_profiler'
            - '/_wdt'
```

See [Security — Caching authenticated routes](SECURITY.md#caching-authenticated-routes) for details.

## Browser and platform notes

| Platform | Install prompt / links | Service worker | Add to home screen |
|----------|------------------------|----------------|--------------------|
| **Chrome / Edge (desktop & Android)** | `beforeinstallprompt` supported | Full support | Via install UI |
| **Firefox** | Limited install UI | Full support | Manual bookmark / install |
| **Safari (macOS / iOS)** | No `beforeinstallprompt`; banner stays hidden | Supported (iOS 11.3+) | **Share → Add to Home Screen** only |
| **iOS installed PWA** | Uninstall via home screen (no programmatic API) | Separate storage from Safari tab | Standalone display mode |

On iOS, rely on translated copy in `install_links` / custom UI to explain **Add to Home Screen**; the bundle cannot trigger a native install dialog.

## Share target (Web Share Target API)

Manifest `share_target` declares that your PWA accepts shares from other apps. **You must implement the action route** in your application — the bundle only emits the manifest entry.

**1. Manifest config:**

```yaml
nowo_pwa:
    manifest:
        share_target:
            action: /share
            method: POST
            enctype: multipart/form-data
            params:
                title: title
                text: text
                url: url
```

**2. Symfony controller (example):**

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShareController extends AbstractController
{
    #[Route('/share', name: 'app_share_target', methods: ['POST'])]
    public function share(Request $request): Response
    {
        $title = $request->request->getString('title');
        $text = $request->request->getString('text');
        $url = $request->request->getString('url');

        // Persist or redirect — e.g. create a draft note from shared content
        return $this->redirectToRoute('app_home', [
            'shared' => $url ?: $text ?: $title,
        ]);
    }
}
```

Validate and sanitize shared input before storing or displaying it.

## Content Security Policy

If you enforce CSP in production, allow the service worker URL and `pwa.js`. See [Security — Content Security Policy](SECURITY.md#content-security-policy).

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
