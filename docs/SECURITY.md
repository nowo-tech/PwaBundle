# Security Policy

## Table of contents

- [Threat model](#threat-model)
- [Caching authenticated routes](#caching-authenticated-routes)
- [Content Security Policy](#content-security-policy)
- [Trusted proxies](#trusted-proxies)
- [Supported versions](#supported-versions)
- [Reporting a vulnerability](#reporting-a-vulnerability)
- [Release security checklist](#release-security-checklist)

## Threat model

| Area | Risk | Mitigation |
|------|------|------------|
| Service worker scope | Over-broad caching or interception | Keep `service_worker.scope` and `manifest.scope` minimal; review `precache_urls` and `runtime_cache_patterns` |
| Authenticated pages cached | Private HTML/API responses stored on device | SW skips `Cache-Control: private`/`no-store`; default `deny_cache_patterns` for auth/admin/API; see [Caching authenticated routes](#caching-authenticated-routes) |
| Offline page | Misleading content if compromised | Override `@NowoPwaBundle/pwa/offline.html.twig` in your app; serve over HTTPS |
| Manifest | User deception (fake app name/icons) | Control `nowo_pwa.yaml`; only deploy trusted icon assets |
| Install prompt | UX spam | Use `install_prompt.dismiss_days`; disable with `install_prompt.enabled: false` if not needed |
| Cached assets | Stale or poisoned responses | Bump `service_worker.cache_version` after security fixes; use HTTPS everywhere |
| Content Security Policy | Service worker or `pwa.js` blocked | Allow `worker-src` and script sources for your SW URL and `/bundles/pwa/pwa.js`; see [Content Security Policy](#content-security-policy) |
| Reverse proxy / Host header | Wrong absolute `start_url` in manifest | Configure Symfony trusted proxies when `manifest.absolute_start_url` is true; see [Trusted proxies](#trusted-proxies) |

This bundle does **not** store user secrets or perform server-side encryption. It generates manifest and service worker responses from configuration.

## Caching authenticated routes

By default the service worker intercepts **navigation** requests (`request.mode === 'navigate'`) within scope. With `network-first`, successful responses can be written to the Cache API.

### Built-in protections

1. **HTTP cache directives** — responses with `Cache-Control: private` or `no-store` are never written to the Cache API. Symfony session/security pages typically send `private`, so login and authenticated HTML are not stored even if a path is missing from deny lists.
2. **Default `deny_cache_patterns`** — auth, admin, API, profiler, and setup paths are denied by substring match (`/login` also matches `/es/login`). Setting an explicit empty list disables those defaults.
3. **Precache filtering** — URLs in `precache_urls` that match a deny pattern are dropped at script build time and again during the SW `install` event.

**Still recommended:** extend deny patterns for app-specific private areas:

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
            # App-specific:
            - '/staff'
            - '/cookie-consent-config'
            - '/account'
```

Also review `precache_urls` and `runtime_cache_patterns` — never precache login or other URLs that return user-specific or unauthenticated redirect HTML. Bump `cache_version` after changing cache rules.

## Content Security Policy

Production apps with a strict CSP must allow the bundle assets and service worker. Example (adjust host and paths to your deployment):

```yaml
# config/packages/framework.yaml (illustrative — use nelmio_security_csp or your CSP layer)
# worker-src 'self' https://your-app.example;
# script-src 'self' 'unsafe-inline';  # module script for pwa.js from same origin
```

Minimum directives to verify:

| Directive | Why |
|-----------|-----|
| `worker-src` | Allows registration of `/sw.js` (or your configured SW path) |
| `script-src` | Allows `type="module"` load of `/bundles/pwa/pwa.js` |

The install prompt and install links use inline `data-*` attributes only; no inline script blocks are injected.

## Trusted proxies

When `manifest.absolute_start_url` is `true` (default), the manifest `start_url` is built from `$request->getSchemeAndHttpHost()`. Behind a reverse proxy, configure [Symfony trusted proxies](https://symfony.com/doc/current/deployment/proxies.html) so the scheme and host reflect the public URL seen by browsers.

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |

## Reporting a vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Send an email to: **hectorfranco@nowo.tech**

Include:

- Type of issue and affected component (manifest, service worker, Twig partial, client script)
- Steps to reproduce
- Impact assessment
- Proof-of-concept if available

We aim to respond within 48 hours.

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|--------|
| **SECURITY.md** | This document is current and linked from the README where applicable. |
| **`.gitignore` and `.env`** | `.env` and local env files are ignored; no committed secrets. |
| **No secrets in repo** | No API keys, passwords, or tokens in tracked files. |
| **Recipe / Flex** | Default recipe or installer templates do not ship production secrets. |
| **HTTPS** | Document that production PWAs require TLS (except localhost). |
| **Scope review** | Default config uses site-wide `/` scope — tighten for multi-tenant apps if needed. |
| **Auth routes** | Defaults deny login/admin/API/profiler; SW skips `Cache-Control: private`/`no-store`. Extend deny patterns for app-specific private paths. |
| **CSP** | Verify `worker-src` and `script-src` in production. |
| **Trusted proxies** | Required when `absolute_start_url` is true behind a load balancer. |
| **Input / output** | Manifest and SW paths validated; Twig overrides documented. |
| **Dependencies** | `composer audit` run; keep Symfony and JS build deps updated. |
| **Logging** | Logs do not print secrets, tokens, or session identifiers unnecessarily. |
| **Cryptography** | N/A — no custom cryptography in this bundle. |
| **Permissions / exposure** | Service worker scope and cache rules documented for integrators. |
| **Limits / DoS** | Cache size and offline asset limits reviewed for production. |

Record confirmation in the release PR or tag notes.

See also [.github/SECURITY.md](../.github/SECURITY.md) for GitHub's security policy template.
