# PHP-FIG PSR evaluation (REQ-CS-007)

Package: `nowo-tech/pwa-bundle` (`symfony-bundle`)

This document records which [PHP-FIG PSRs](https://www.php-fig.org/psr/) apply to this package.
Only contracts that add clear interoperability or maintainability value are **Adopted**.
Others are **N/A** (or already covered by Symfony) so the decision stays auditable.

## Table of contents

- [Baseline (always)](#baseline-always)
- [Interface / contract PSRs](#interface--contract-psrs)
- [Summary](#summary)

## Baseline (always)

| PSR | Decision | How |
| --- | -------- | --- |
| PSR-12 (coding style) | **Adopted** | `@PSR12` in `.php-cs-fixer.dist.php` (Nowo REQ-CS-001). |
| PSR-4 (autoloading) | **Adopted** | `composer.json` `autoload` / `autoload-dev` PSR-4 map for package sources and tests. |

## Interface / contract PSRs

| PSR | Decision | Notes |
| --- | -------- | ----- |
| PSR-3 Logger | **Adopted** | `MinishlinkWebPushSender` takes an optional `Psr\Log\LoggerInterface` (the `logger` service) and passes it to minishlink/web-push for delivery diagnostics. |
| PSR-6 / PSR-16 Cache | **N/A** | No package-owned cache layer. |
| PSR-7 / PSR-17 HTTP messages | **Adopted** (Web Push only) | minishlink/web-push builds PSR-7 requests from the injected PSR-17 `RequestFactoryInterface` / `StreamFactoryInterface`; the bundle's own controllers use HttpFoundation. |
| PSR-18 HTTP client | **Adopted** | Web Push deliveries go through the host `Psr\Http\Client\ClientInterface` (any PSR-18 client; Symfony `Psr18Client` recommended), with PSR-17 request/stream factories; a Symfony `Psr18Client` gets the bundle deadlines `web_push.timeout` / `connect_timeout` via `withOptions()`, other clients keep their own (see CONFIGURATION.md › HTTP timeouts). |
| PSR-11 Container | **N/A** | No container locator in the public API; constructor injection only. |
| PSR-14 Event dispatcher | **N/A** | No domain event extension points that need a dispatcher SPI. |
| PSR-15 HTTP middleware | **N/A** | Uses Symfony kernel listeners/subscribers (or has no HTTP middleware pipeline). |
| PSR-20 Clock | **N/A** | No time-sensitive domain logic requiring a clock SPI. |

## Summary

- **Adopted beyond baseline:** none (baseline PSR-12 + PSR-4 only).
- **Rule:** do not add `psr/*` Composer dependencies without matching type-hints and DI wiring.
- **Re-evaluate** when the package gains logging, HTTP, cache, clock, or event SPIs.

---

_REQ-CS-007 evaluation date: 2026-10-22 (PSR-3, PSR-7/17 and PSR-18 re-evaluated for Web Push)._
