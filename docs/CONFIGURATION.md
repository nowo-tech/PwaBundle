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
        # Defaults deny auth/admin/API/profiler/setup. Explicit [] disables defaults.
        # deny_cache_patterns: ['/login', '/admin', '/api/', '/_profiler', '/_wdt']
        offline_url: /offline
        skip_waiting: true
        clients_claim: true
        navigation_preload: false
        runtime_cache_max_entries: 0   # 0 = unlimited
        # Optional kit Web Push handlers (JSON: title, body, url, icon, badge, tag).
        # web_push: true
        # web_push_defaults:
        #     title: Notification
        #     icon: /icons/icon-192.png
        #     badge: /icons/icon-192.png
        #     url: /
        #     tag: nowo-pwa
        # Optional extra JS after kit web_push (raw string or env(file:…) contents).
        # append_script: null
```

Bump `cache_version` when precache lists or `web_push` / `append_script` change to invalidate old caches.

### Web Push SW handlers (`web_push`)

When `service_worker.web_push: true`, the kit appends `src/Resources/js/web_push_sw_append.js` to the generated service worker. The push event expects a JSON body:

| Field | Purpose |
| ----- | ------- |
| `title` | Notification title (fallback: `web_push_defaults.title`) |
| `body` | Notification body |
| `url` | Click target (same-origin; off-origin falls back to `/`) |
| `icon` / `badge` | Optional icons |
| `tag` | Notification tag / renotify key |

Hosts should **compose** `title` / `body` / `url` in PHP (or another producer) before sending the push. Do not put product domain mapping in a forked SW. Extra host JS still goes in `append_script` (concatenated after the kit script).

## Install prompt

```yaml
nowo_pwa:
    install_prompt:
        enabled: true
        display: banner              # banner | flash | modal
        dismiss_key: nowo_pwa_install_dismissed
        dismiss_days: 7              # remind again after N days (0 = always show)
        never_dismiss_key: nowo_pwa_install_never
        show_never_option: true      # "Don't ask again" button
        position: bottom             # top | bottom (banner mode only)
        css_class: nowo-pwa-install
        mark_asset: null             # optional brand mark <img src>
        title: null                  # optional title string or translation key
        eyebrow: null                # optional eyebrow string or translation key
        button_class: ''             # empty = derive BEM class from css_class
        dismiss_button_class: null   # null = derive BEM class from css_class
        never_button_class: null     # null = derive BEM class from css_class
        delay_ms: 0
        visibility: all              # all | mobile | desktop
        route_targeting:
            match_by: name           # name | path
            mode: only               # all | only | except
            routes: [app_home]
```

| Key | Default | Description |
|-----|---------|-------------|
| `display` | `banner` | `banner` (fixed bar), `flash` (inline, place `{{ nowo_pwa_install_prompt() }}` in content), `modal` (dialog) |
| `css_class` | `nowo-pwa-install` | Root class(es). BEM suffixes (`__mark`, `--banner`, …) use the **first** token only — prefer a single root class |
| `mark_asset` | `null` | Optional `<img src>` for the prompt brand mark; empty / null disables the image |
| `title` | `null` | Optional title override; when omitted, the bundle falls back to the translated install action label |
| `eyebrow` | `null` | Optional eyebrow text shown above the title |
| `button_class` | `''` | Install button class; empty string derives BEM classes from `css_class` |
| `dismiss_button_class` | `null` | Remind-later button class; `null` derives BEM classes from `css_class` |
| `never_button_class` | `null` | Never-again button class; `null` derives BEM classes from `css_class` |
| `dismiss_days` | 7 | Days before showing again after "Not now" |
| `never_dismiss_key` | `nowo_pwa_install_never` | localStorage key when user chooses "Don't ask again" |
| `show_never_option` | true | Show permanent dismiss button |
| `route_targeting` | all routes | Limit where the prompt HTML is rendered (independent from global targeting) |

Path patterns (when `match_by: path`): exact (`/vault`), prefix (`/vault*`), regex (`/^\\/admin/`).

### Install prompt blocks and CSS tokens

Override only the parts you need instead of forking the whole template:

- `pwa_install_mark`
- `pwa_install_eyebrow`
- `pwa_install_title`
- `pwa_install_actions`

The bundled stylesheet exposes neutral custom properties such as:

- `--nowo-pwa-install-bg`
- `--nowo-pwa-install-color`
- `--nowo-pwa-install-mark-size`
- `--nowo-pwa-install-eyebrow-color`
- `--nowo-pwa-install-primary-bg`
- `--nowo-pwa-install-secondary-color`

Remap them from your app theme:

```css
:root {
    --nowo-pwa-install-bg: var(--brand-surface-900);
    --nowo-pwa-install-primary-bg: var(--brand-primary-600);
    --nowo-pwa-install-mark-size: 3rem;
}
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
        route_targeting:
            match_by: name
            mode: only
            routes: [app_home]
```

- **Install link** appears when the browser fires `beforeinstallprompt` (Chromium).
- **Uninstall link** appears when the app is detected as installed (standalone display mode, iOS home screen, or `getInstalledRelatedApps`).
- Uninstall opens a translated help dialog (browsers do not expose a programmatic uninstall API from the page).

Override template: `templates/bundles/NowoPwaBundle/pwa/install_links.html.twig`.

Install links also inherit CSS tokens from `pwa.css`, including `--nowo-pwa-install-links-install-bg`, `--nowo-pwa-install-links-install-color`, `--nowo-pwa-install-links-uninstall-bg`, and `--nowo-pwa-install-links-uninstall-color`.

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

Limit PWA head tags and client script to specific Symfony routes or URL paths:

```yaml
nowo_pwa:
    route_targeting:
        match_by: name    # name (default) | path
        mode: except      # all | only | except
        routes:
            - admin_dashboard
```

Path patterns when `match_by: path`:

| Pattern | Matches |
|---------|---------|
| `/vault` | Exact path |
| `/vault*` | Prefix |
| `/^\\/admin/` | Regex (PCRE) |

Per-component targeting: `install_prompt.route_targeting` and `install_links.route_targeting` use the same shape and override visibility for those helpers only.

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
        install_links: '@NowoPwaBundle/pwa/install_links.html.twig'
        offline: '@NowoPwaBundle/pwa/offline.html.twig'
```

The bundled offline page exposes two Twig blocks for light-touch overrides:

- `pwa_offline_brand`
- `pwa_offline_content`

## Share target and file handlers

The bundle serializes `share_target`, `file_handlers`, and `protocol_handlers` into the manifest. **Your app must implement the target routes** (controllers or forms) that receive shared content or opened files. See [Usage — Share target](USAGE.md#share-target-web-share-target-api) for a full Symfony example.

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
        deny_cache_patterns: ['/login', '/logout', '/register', '/reset-password', '/admin', '/api/', '/_profiler', '/_wdt', '/setup', '/_site_backup']
        navigation_preload: true
        cache_version: v1

    install_prompt:
        enabled: true
        position: bottom
        visibility: mobile
        delay_ms: 1500
        mark_asset: '/icons/mark.svg'
        eyebrow: 'Available offline'

    client:
        check_updates_on_visibility: true
        reload_on_update: false

    http:
        manifest_cache_max_age: 3600
        service_worker_cache_max_age: 0
```

See [Usage](USAGE.md) for Twig integration and overrides.
