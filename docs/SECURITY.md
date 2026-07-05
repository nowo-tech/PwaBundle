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
| Authenticated pages cached | Private HTML/API responses stored on device | Use `deny_cache_patterns` for `/admin`, `/api/private`, profiler routes; see [Caching authenticated routes](#caching-authenticated-routes) |
| Offline page | Misleading content if compromised | Override `@NowoPwaBundle/pwa/offline.html.twig` in your app; serve over HTTPS |
| Manifest | User deception (fake app name/icons) | Control `nowo_pwa.yaml`; only deploy trusted icon assets |
| Install prompt | UX spam | Use `install_prompt.dismiss_days`; disable with `install_prompt.enabled: false` if not needed |
| Cached assets | Stale or poisoned responses | Bump `service_worker.cache_version` after security fixes; use HTTPS everywhere |
| Content Security Policy | Service worker or `pwa.js` blocked | Allow `worker-src` and script sources for your SW URL and `/bundles/pwa/pwa.js`; see [Content Security Policy](#content-security-policy) |
| Reverse proxy / Host header | Wrong absolute `start_url` in manifest | Configure Symfony trusted proxies when `manifest.absolute_start_url` is true; see [Trusted proxies](#trusted-proxies) |

This bundle does **not** store user secrets or perform server-side encryption. It generates manifest and service worker responses from configuration.

## Caching authenticated routes

By default the service worker intercepts **navigation** requests (`request.mode === 'navigate'`) within scope. With `network-first`, successful responses can be written to the Cache API — including pages that require a session cookie.

**Recommended:** exclude sensitive paths from caching:

```yaml
nowo_pwa:
    service_worker:
        deny_cache_patterns:
            - '/admin'
            - '/api/private'
            - '/_profiler'
            - '/_wdt'
```

Also review `precache_urls` and `runtime_cache_patterns` — avoid precaching URLs that return user-specific content.

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

## Release security checklist

Before tagging a release:

| Item | Notes |
|------|--------|
| **HTTPS** | Document that production PWAs require TLS (except localhost). |
| **No secrets in repo** | No API keys or tokens in tracked files or demo `.env`. |
| **Scope review** | Default config uses site-wide `/` scope — tighten for multi-tenant apps if needed. |
| **Auth routes** | Add `deny_cache_patterns` for admin, API, and profiler paths. |
| **CSP** | Verify `worker-src` and `script-src` in production. |
| **Trusted proxies** | Required when `absolute_start_url` is true behind a load balancer. |
| **Dependencies** | Run `composer audit`; keep Symfony and JS build deps updated. |
| **Overrides** | Document safe override paths for Twig templates in [USAGE.md](USAGE.md). |

See also [.github/SECURITY.md](../.github/SECURITY.md) for GitHub's security policy template.
