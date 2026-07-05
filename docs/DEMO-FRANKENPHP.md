# Demo with FrankenPHP

The bundle includes a **Symfony 8.1** demo under `demo/symfony8/` using **FrankenPHP** and Docker Compose. No database is required.

## Table of contents

- [Quick start](#quick-start)
- [What the demo shows](#what-the-demo-shows)
- [Development vs production](#development-vs-production)
- [Commands](#commands)
- [Troubleshooting](#troubleshooting)

## Quick start

From the bundle root:

```bash
make -C demo up-symfony8
```

Default URL: **http://localhost:8025** (override with `PORT` in `demo/symfony8/.env`).

Or from the demo directory:

```bash
cd demo/symfony8
make up
```

The demo installs Composer dependencies, runs `assets:install`, and serves the app with FrankenPHP.

In **development** (`APP_ENV=dev`), the container uses `Caddyfile.dev` without FrankenPHP worker mode so PHP and Twig changes appear on refresh. The dev stack also includes **Web Profiler**, **Symfony Debug**, and **Nowo Twig Inspector** (REQ-DEMO-001).

## What the demo shows

| Route | Description |
|-------|-------------|
| `/` | Home page with `nowo_pwa_head()`, install links, and install prompt banner |
| `/_profiler` | Symfony Web Profiler (dev only) |
| `/_template/{name}` | Twig Inspector template preview (dev only) |
| `/manifest.webmanifest` | Generated Web App Manifest (JSON) |
| `/sw.js` | Generated service worker script |
| `/offline` | Offline fallback page |

Open DevTools → **Application** to inspect manifest registration and the service worker.

## Development vs production

| File | Mode | Use |
|------|------|-----|
| `docker/frankenphp/Caddyfile` | Worker | Production-like performance |
| `docker/frankenphp/Caddyfile.dev` | Request | Local development (default in demo) |

To run with worker mode:

```bash
cd demo/symfony8
APP_ENV=prod APP_DEBUG=0 docker compose up -d --build
```

## Commands

```bash
make -C demo/symfony8 up              # start demo
make -C demo/symfony8 down            # stop containers
make -C demo/symfony8 shell           # PHP container shell
make -C demo/symfony8 update-bundle   # sync local bundle + clear cache
```

From bundle root:

```bash
make -C demo release-check            # healthcheck: /, manifest, sw.js
```

## Troubleshooting

- **Port in use:** set `PORT=8026` (or another free port) in `demo/symfony8/.env` and restart.
- **Stale assets:** `make -C demo/symfony8 update-bundle` and rebuild bundle TS with `make assets` at bundle root.
- **Packagist / DNS in Docker:** demo `docker-compose.yml` sets public DNS for Composer inside the container.
- **Service worker not registering:** use HTTPS or `localhost`; secure context is required.

See also [Password Toggle Bundle DEMO-FRANKENPHP](https://github.com/nowo-tech/PasswordToggleBundle/blob/main/docs/DEMO-FRANKENPHP.md) for the general FrankenPHP demo pattern used across Nowo bundles.
