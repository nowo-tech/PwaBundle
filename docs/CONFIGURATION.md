# Configuration

All options live under the `nowo_pwa` root key in `config/packages/nowo_pwa.yaml`.

## Table of contents

- [Master switch](#master-switch)
- [Manifest (W3C Web App Manifest)](#manifest-w3c-web-app-manifest)
- [Meta tags (HTML head)](#meta-tags-html-head)
- [Service worker](#service-worker)
- [Install prompt](#install-prompt)
- [Install links](#install-links)
- [Client script](#client-script)
- [HTTP caching](#http-caching)
- [Route targeting](#route-targeting)
- [Routes & templates](#routes--templates)
- [Full example](#full-example)

## Master switch

```yaml
nowo_pwa:
    enabled: true          # false disables manifest, SW, and Twig helpers
    route_prefix: ''       # optional prefix for /manifest.webmanifest, /sw.js, /offline
```

## Manifest (W3C Web App Manifest)

| Key | Default | Description |
|-----|---------|-------------|
| `name` | My Application | Full application name |
| `short_name` | App | Home screen label |
| `description` | — | Store / install description |
| `lang` | en | BCP 47 language tag |
| `dir` | ltr | `ltr`, `rtl`, or `auto` |
| `start_url` | `/` | Launch URL path |
| `absolute_start_url` | true | When true, manifest `start_url` is absolute (recommended) |
| `scope` | `/` | Navigation scope |
| `id` | `/` | Manifest id (defaults to start URL) |
| `display` | standalone | `fullscreen`, `standalone`, `minimal-ui`, `browser` |
| `display_override` | `[]` | Ordered fallback display modes (incl. `window-controls-overlay`) |
| `orientation` | any | Screen orientation lock |
| `theme_color` | `#0f172a` | Browser UI colour |
| `background_color` | `#ffffff` | Splash screen background |
| `categories` | `[]` | Store categories |
| `iarc_rating_id` | — | IARC rating UUID |
| `prefer_related_applications` | false | Prefer native apps when true |
| `icons` | `[]` | `{ src, sizes, type, purpose }` |
| `screenshots` | `[]` | `{ src, sizes, type, label?, form_factor? }` — `narrow` / `wide` |
| `shortcuts` | `[]` | `{ name, url, short_name?, description?, icons? }` |
| `related_applications` | `[]` | `{ platform, url?, id? }` |
| `scope_extensions` | `[]` | `{ origin, type? }` — extended scope origins |
| `launch_handler` | `{ client_mode: auto }` | `auto`, `navigate-existing`, `navigate-new`, `focus-existing` |
| `protocol_handlers` | `[]` | `{ protocol, url }` — custom protocol handling |
| `file_handlers` | `[]` | `{ action, accept_map: { mime: [ext] }, icons? }` |
| `share_target` | — | Web Share Target API (see below) |
| `edge_side_panel` | — | `{ preferred_width }` — Microsoft Edge side panel |

### Share target

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
                files: files
```

Your application must implement the `action` route to receive shared content.

## Meta tags (HTML head)

Injected by `nowo_pwa_head()` in addition to manifest link.

| Key | Default | Description |
|-----|---------|-------------|
| `mobile_web_app_capable` | true | `<meta name="mobile-web-app-capable">` |
| `apple_mobile_web_app_capable` | true | Apple standalone mode |
| `apple_status_bar_style` | default | `default`, `black`, `black-translucent` |
| `apple_mobile_web_app_title` | — | Overrides manifest `short_name` for Apple |
| `viewport_fit` | — | `auto`, `cover`, `contain` — adds viewport-fit for notched devices |
| `theme_color_light` | — | Theme colour for `(prefers-color-scheme: light)` |
| `theme_color_dark` | — | Theme colour for `(prefers-color-scheme: dark)` |
| `color_scheme` | — | e.g. `light dark` |
| `msapplication_tile_color` | — | Windows tile colour |
| `msapplication_tile_image` | — | Windows tile image URL |
| `msapplication_config` | — | Path to `browserconfig.xml` |
| `referrer` | — | Referrer policy meta tag |
| `format_detection` | — | `{ telephone, email, address }` booleans |
| `apple_touch_icons` | `[]` | `{ href, sizes? }` — overrides default from manifest icons |
| `apple_startup_images` | `[]` | `{ href, media? }` splash screens |
| `mask_icon` | — | `{ href, color? }` Safari pinned tab |
| `extra_link_tags` | `[]` | `{ rel, href, type?, sizes? }` arbitrary link tags |

## Service worker

```yaml
nowo_pwa:
    service_worker:
        enabled: true
        scope: /
        cache_version: v1
        cache_name_prefix: nowo-pwa
        strategy: network-first   # cache-first | stale-while-revalidate
        precache_urls: ['/', '/offline']
        runtime_cache_patterns: ['/build/', '/assets/']
        deny_cache_patterns: ['/api/']
        offline_url: /offline
        skip_waiting: true
        clients_claim: true
        navigation_preload: false
        runtime_cache_max_entries: 0   # 0 = unlimited
```

Bump `cache_version` when precache lists change to invalidate old caches.

## Install prompt

```yaml
nowo_pwa:
    install_prompt:
        enabled: true
        dismiss_key: nowo_pwa_install_dismissed
        dismiss_days: 7
        position: bottom          # top | bottom
        css_class: nowo-pwa-install
        delay_ms: 0
        visibility: all           # all | mobile | desktop
```

## Install links

Inject with `{{ nowo_pwa_install_links() }}` in your layout (e.g. footer or navigation). The bundle shows **Install app** or **Uninstall app** — never both at once.

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | true | Render install / uninstall links |
| `css_class` | `nowo-pwa-install-links` | CSS class on the container |
| `visibility` | all | `all`, `mobile`, or `desktop` |

```yaml
nowo_pwa:
    install_links:
        enabled: true
        css_class: nowo-pwa-install-links
        visibility: all
```

- **Install link** appears when the browser fires `beforeinstallprompt` (Chromium).
- **Uninstall link** appears when the app is detected as installed (standalone display mode, iOS home screen, or `getInstalledRelatedApps`).
- Uninstall opens a translated help dialog (browsers do not expose a programmatic uninstall API from the page).

Override template: `templates/bundles/NowoPwaBundle/pwa/install_links.html.twig`.

## Client script

Options passed to `pwa.js` via data attributes:

```yaml
nowo_pwa:
    client:
        register_on_load: true
        check_updates_on_visibility: true
        reload_on_update: false
```

## HTTP caching

Cache headers for dynamically generated manifest and service worker responses:

```yaml
nowo_pwa:
    http:
        manifest_cache_max_age: 3600
        service_worker_cache_max_age: 0
        manifest_public_cache: true
```

## Route targeting

Limit PWA injection to specific Symfony routes:

```yaml
nowo_pwa:
    route_targeting:
        mode: except    # all | only | except
        routes:
            - admin_dashboard
```

## Routes & templates

```yaml
nowo_pwa:
    routes:
        manifest:
            path: /manifest.webmanifest
            name: nowo_pwa_manifest
        service_worker:
            path: /sw.js
            name: nowo_pwa_service_worker
        offline:
            path: /offline
            name: nowo_pwa_offline
    templates:
        head: '@NowoPwaBundle/pwa/head.html.twig'
        install_prompt: '@NowoPwaBundle/pwa/install_prompt.html.twig'
        offline: '@NowoPwaBundle/pwa/offline.html.twig'
```

## Full example

```yaml
nowo_pwa:
    enabled: true

    manifest:
        name: '%env(default::APP_NAME)%'
        short_name: App
        description: 'Full-featured Symfony PWA'
        lang: es
        dir: ltr
        start_url: /
        scope: /
        display: standalone
        display_override: [standalone, browser]
        theme_color: '#0f172a'
        background_color: '#ffffff'
        categories: [productivity, utilities]
        icons:
            - { src: '/icons/icon-192.png', sizes: '192x192', type: image/png, purpose: any }
            - { src: '/icons/icon-512.png', sizes: '512x512', type: image/png, purpose: any maskable }
        screenshots:
            - { src: '/screenshots/home-wide.png', sizes: '1280x720', type: image/png, form_factor: wide, label: Home }
        shortcuts:
            - { name: Dashboard, url: /dashboard, icons: [{ src: '/icons/shortcut.png', sizes: '96x96' }] }
        protocol_handlers:
            - { protocol: web+myapp, url: '/handle?url=%s' }
        file_handlers:
            - action: /open-file
              accept_map:
                  application/json: ['.json']
        launch_handler:
            client_mode: navigate-existing

    meta:
        theme_color_light: '#ffffff'
        theme_color_dark: '#0f172a'
        color_scheme: 'light dark'
        apple_startup_images:
            - { href: '/splash/iphone.png', media: '(device-width: 390px)' }
        mask_icon:
            href: '/icons/safari-pinned.svg'
            color: '#0f172a'
        format_detection:
            telephone: false

    service_worker:
        strategy: network-first
        precache_urls: ['/', '/offline']
        runtime_cache_patterns: ['/build/', '/bundles/']
        deny_cache_patterns: ['/api/']
        navigation_preload: true
        cache_version: v1

    install_prompt:
        enabled: true
        position: bottom
        visibility: mobile
        delay_ms: 1500

    client:
        check_updates_on_visibility: true
        reload_on_update: false

    http:
        manifest_cache_max_age: 3600
        service_worker_cache_max_age: 0
```

See [Usage](USAGE.md) for Twig integration and overrides.
