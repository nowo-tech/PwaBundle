# Security Policy

## Table of contents

- [Threat model](#threat-model)
- [Supported versions](#supported-versions)
- [Reporting a vulnerability](#reporting-a-vulnerability)
- [Release security checklist](#release-security-checklist)

## Threat model

| Area | Risk | Mitigation |
|------|------|------------|
| Service worker scope | Over-broad caching or interception | Keep `service_worker.scope` and `manifest.scope` minimal; review `precache_urls` and `runtime_cache_patterns` |
| Offline page | Misleading content if compromised | Override `@NowoPwaBundle/pwa/offline.html.twig` in your app; serve over HTTPS |
| Manifest | User deception (fake app name/icons) | Control `nowo_pwa.yaml`; only deploy trusted icon assets |
| Install prompt | UX spam | Use `install_prompt.dismiss_days`; disable with `install_prompt.enabled: false` if not needed |
| Cached assets | Stale or poisoned responses | Bump `service_worker.cache_version` after security fixes; use HTTPS everywhere |

This bundle does **not** store user secrets or perform server-side encryption. It generates manifest and service worker responses from configuration.

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
| **Dependencies** | Run `composer audit`; keep Symfony and JS build deps updated. |
| **Overrides** | Document safe override paths for Twig templates in [USAGE.md](USAGE.md). |

See also [.github/SECURITY.md](../.github/SECURITY.md) for GitHub's security policy template.
