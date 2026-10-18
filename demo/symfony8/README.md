# PWA Bundle — Symfony 8.1 Demo

Minimal Symfony application demonstrating `nowo-tech/pwa-bundle` with FrankenPHP.

## Quick start

```bash
make up
```

Open **http://localhost:8025** (or the `PORT` from `.env`).

## What to verify

1. Page source includes manifest link and `pwa.js` module script.
2. `/manifest.webmanifest` returns JSON with `name`, `icons`, `theme_color`.
3. `/sw.js` returns generated service worker JavaScript.
4. DevTools → Application shows manifest and (on localhost) service worker registration.
5. **Install button** — on the home page, `nowo_pwa_install_links()` shows **Install app** when the browser fires `beforeinstallprompt` (Chrome/Edge on localhost or HTTPS). The dismissible banner (`nowo_pwa_install_prompt()`) opens on that event; **Preview the install prompt** shows it in any browser.
6. **UiKit page contract** — the layout extends `@NowoUiKit/layout/document.html.twig`; `config/packages/nowo_ui_kit.yaml` sets the host theme (tokens + class map) that the install prompt, install links and offline page read. `templates/pwa/install_prompt.html.twig` overrides **one** block (`pwa_install_mark`) through `@!NowoPwaBundle` and `nowo_pwa.web_ui.templates.install_prompt`.
7. **Offline page** — `/offline`: self-contained UiKit status page (inline CSS, no requests), precached by the service worker.
8. **Web Profiler** — toolbar item **PWA** with manifest, service worker, and route-targeting status (dev only).
9. **Twig Inspector** (dev only): toolbar overlay to inspect Twig templates — requires `APP_DEBUG=1` (default in this demo).

If you updated the service worker or icons, unregister the old worker in DevTools → Application → Service Workers, then hard-refresh.

## Dev stack (REQ-DEMO-001)

- Web Profiler (`/_profiler`)
- Symfony Debug (`APP_DEBUG=1`)
- [Twig Inspector](https://github.com/nowo-tech/TwigInspectorBundle) (`nowo-tech/twig-inspector-bundle`)

```bash
make down
make shell
make update-bundle
make test
make test-e2e          # Playwright e2e (REQ-DEMO-013)
make demo-screenshots  # re-capture ../../docs/images/demo/*.webp
```

The local bundle is mounted at `/var/pwa-bundle` inside the container.
