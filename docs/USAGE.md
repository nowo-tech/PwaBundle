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

Limit PWA injection to specific pages by Symfony route name or URL path:

```yaml
nowo_pwa:
    route_targeting:
        match_by: name    # name (default) | path
        mode: except
        routes:
            - admin_dashboard
            - api_docs
```

Path examples (`match_by: path`): exact `/vault`, prefix `/vault*`, regex `regex:/^\/admin/` or `#^/admin(/|$)#`. Regexes need an explicit marker: a plain `/blog/` is the path `/blog` (see [Configuration](CONFIGURATION.md)).

Modes: `all` (default), `only`, `except`.

`install_prompt` and `install_links` support their own `route_targeting` block (independent from the global one). See [Configuration — Install prompt](CONFIGURATION.md#install-prompt).

## Install prompt display modes

| Mode | Where to place Twig | Behaviour |
|------|---------------------|-----------|
| `banner` (default) | Layout footer / end of `<body>` | Fixed bar at top or bottom |
| `flash` | Inline in page content | Alert-style block where the helper is called |
| `modal` | Layout footer / end of `<body>` | Centered dialog with backdrop |

```yaml
nowo_pwa:
    install_prompt:
        display: modal
        show_never_option: true
```

## Service worker strategies

| Strategy | Behaviour |
|----------|-----------|
| `network-first` | Try network; fall back to cache / offline page on failure |
| `cache-first` | Serve cache when available |
| `stale-while-revalidate` | Return cache immediately, refresh in background |

Bump `service_worker.cache_version` when you change precache lists to invalidate old caches.

### Exclude authenticated routes from cache

The service worker skips responses with `Cache-Control: private` / `no-store`, and ships default `deny_cache_patterns` for login, admin, API, and profiler paths. Extend the list for app-specific private areas (config replaces defaults; it does not merge):

```yaml
nowo_pwa:
    service_worker:
        deny_cache_patterns:
            - '/login'
            - '/logout'
            - '/register'
            - '/reset-password'
            - '/admin'
            - '/api/'
            - '/_profiler'
            - '/_wdt'
            - '/setup'
            - '/_site_backup'
            - '/staff'   # example app path
```

Do not put auth URLs in `precache_urls`. See [Security — Caching authenticated routes](SECURITY.md#caching-authenticated-routes).

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

## Push diagnostics, test notification and browser hints

Optional building blocks extracted from Symfony Beacon (all host-agnostic, translated in the 7 bundle locales, domain `NowoPwaBundle`).

### 1. Server side (optional)

Enable the sender and the test endpoint (see [Configuration](CONFIGURATION.md#web-push-sender-and-test-notification)) and bridge your subscription storage:

```php
use Nowo\PwaBundle\WebPush\Exception\PushTestDeniedException;
use Nowo\PwaBundle\WebPush\PushSubscriptionData;
use Nowo\PwaBundle\WebPush\PushSubscriptionProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AppPushSubscriptionProvider implements PushSubscriptionProviderInterface
{
    public function __construct(private PushSubscriptionRepository $repository, private EntityManagerInterface $em) {}

    public function findSubscriptions(UserInterface $user): iterable
    {
        if (!$user->isPushNotificationsEnabled()) {
            throw new PushTestDeniedException('preference_disabled'); // JSON {"error":"preference_disabled"}, HTTP 400
        }
        foreach ($this->repository->findByUser($user) as $row) {
            yield new PushSubscriptionData($row->getEndpoint(), $row->getP256dh(), $row->getAuthToken(), $row->getContentEncoding());
        }
    }

    public function removeSubscription(UserInterface $user, PushSubscriptionData $subscription): void
    {
        $this->repository->deleteByEndpointHashForUser(hash('sha256', $subscription->endpoint), $user);
    }
}
```

`POST nowo_pwa_push_test` (default `/pwa/push/test`) answers JSON:

| Status | Body |
|--------|------|
| 200 | `{ok: true, results: [{host, ok, status, reason, expired}]}` (at least one device accepted) |
| 502 | `{ok: false, results: […]}` (every push service rejected it) |
| 401 / 403 | `{ok: false, error: "unauthenticated" \| "forbidden" \| "invalid_csrf"}` |
| 503 / 404 | `{ok: false, error: "push_not_configured" \| "no_subscription"}` |
| 400 (or custom) | `{ok: false, error: <PushTestDeniedException::$errorCode>}` |
| 500 | `{ok: false, error: "client_error", reason: "…"}` (missing PSR-18/PSR-17 implementation) |

Subscriptions reported as gone (HTTP 404/410) or whose endpoint is not an allowed push host are passed to `removeSubscription()`. You can also inject `Nowo\PwaBundle\WebPush\WebPushSenderInterface` in your own handlers (`send(PushSubscriptionData, string $jsonPayload, ['TTL' => …, 'urgency' => …])`).

### 2. Stimulus peers (Vite / AssetMapper)

TypeScript sources (not part of `pwa.js`; the host compiles them and provides `@hotwired/stimulus`):

| Path under `vendor/nowo-tech/pwa-bundle/src/Resources/assets/` | Purpose |
|----------------------------------------------------------------|---------|
| `src/push-checks.ts` | `runPushChecks()`, `applicableChecks()`, `urlBase64ToUint8Array()`, `PUSH_CHECK_ORDER`, types `PushConfig` / `PushCheck` / `PushCheckReport` |
| `src/browser-detect.ts` | `detectBrowser()`, `BROWSER_IDS`, type `BrowserId` |
| `stimulus-peers/push_diagnostics_controller.ts` | Stimulus controller, identifier **`push-diagnostics`** |
| `stimulus-peers/browser_hint_controller.ts` | Stimulus controller, identifier **`browser-hint`** (also re-exports `detectBrowser`) |

Vite + pnpm host (`assets/stimulus_bootstrap.ts`):

```ts
import PushDiagnosticsController from '../vendor/nowo-tech/pwa-bundle/src/Resources/assets/stimulus-peers/push_diagnostics_controller';
import BrowserHintController from '../vendor/nowo-tech/pwa-bundle/src/Resources/assets/stimulus-peers/browser_hint_controller';

app.register('push-diagnostics', PushDiagnosticsController);
app.register('browser-hint', BrowserHintController);
```

Or keep `assets/controllers/*_controller.ts` files that re-export the peer (`export { default } from '../../vendor/nowo-tech/pwa-bundle/src/Resources/assets/stimulus-peers/push_diagnostics_controller';`). Keep the vendor layout intact (the peers import `../src/*.ts`). Silent re-subscription code can reuse the same flow: `import { runPushChecks } from '../vendor/nowo-tech/pwa-bundle/src/Resources/assets/src/push-checks';`.

`push-checks` steps: `support` (SW + PushManager + Notification + secure context) → `server_config` (VAPID key) → `preference` (only when the config has `preferenceEnabled`) → `permission` → `service_worker` (ready, 8 s timeout) → `browser_subscription` (re-created when the browser subscription was made with another VAPID key) → `server_subscription` (only with a `subscribeUrl`). The first failure stops the run; later steps are reported as `skip`.

### 3. Twig partials

```twig
{# VAPID key + test URL + CSRF token come from nowo_pwa_web_push() when web_push is enabled #}
{% include '@NowoPwaBundle/pwa/push_diagnostics.html.twig' %}

{# Host-owned config/subscribe endpoints (e.g. a preference flag and a custom CSRF id) #}
{% include '@NowoPwaBundle/pwa/push_diagnostics.html.twig' with {
    config_url: path('account_realtime_config'),
    subscribe_url: path('account_push_subscribe'),
    csrf_token: csrf_token('account_push'),
    auto: user.pushNotificationsEnabled,
    css_class: 'panel',
    check_class_prefix: 'push-check',
    button_class: 'btn-ghost',
} %}

{% include '@NowoPwaBundle/pwa/browser_hint.html.twig' with {guide_url: path('app_browser_guide')} %}
{% include '@NowoPwaBundle/pwa/browser_guide.html.twig' %}   {# per-browser install + push steps, anchors #browser-<id> #}
```

`nowo_pwa_web_push()` returns `{enabled, configured, vapid_public_key, test_url, csrf_token_id, csrf_token}`.

The diagnostics controller sets `<html data-push-blocked="1">` when the browser refuses the subscription; `browser-hint` sets `<html data-browser="brave|chrome|edge|firefox|safari|ios|opera|samsung|other">`, so CSS can show browser-specific hints. Default styles live in `pwa.css` (`.nowo-pwa-push-check*`, `.nowo-pwa-browser-hint*`).

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

For lighter customisation, prefer the bundled Twig blocks instead of copying full templates:

- Install prompt: `pwa_install_mark`, `pwa_install_eyebrow`, `pwa_install_title`, `pwa_install_actions`
- Offline page: `pwa_offline_brand`, `pwa_offline_content`

The default stylesheet also exposes `--nowo-pwa-*` custom properties for brand remapping. See [Configuration — Install prompt](CONFIGURATION.md#install-prompt).

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
