# Spec-driven development — PwaBundle

In this repository, **spec-driven development** has three layers that stay in sync:

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) ([`spec.md`](../specs/001-baseline/spec.md), [`code-inventory.md`](../specs/001-baseline/code-inventory.md)), initialized with [GitHub Spec Kit](https://github.com/github/spec-kit) (`.specify/`, **Cursor Agent** skills in `.cursor/skills/speckit-*`). The inventory maps **100%** of production code in `src/`. **How to install, initialize, and use Spec Kit:** [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Product behavior** — what **PwaBundle** guarantees to Symfony applications (manifest, service worker, Twig helpers, client script). This is spelled out below and in [`USAGE.md`](USAGE.md) / [`CONFIGURATION.md`](CONFIGURATION.md); **PHPUnit**, **Vitest**, and **PHPStan** enforce it in CI.
3. **Traceability anchors** — stable **`REQ-*`** identifiers in Makefiles and demos so changes to scripts, ports, and demo workflows stay discoverable from issues and PRs.

There is no separate executable spec language (for example Gherkin); tests and static analysis are the mechanical proof.

---

## User stories

| ID | Story |
| --- | --- |
| US-01 | **As a** Symfony integrator, **I want** to configure the Web App Manifest from YAML (`nowo_pwa.manifest`) **so that** `/manifest.webmanifest` reflects my app name, icons, theme, and advanced W3C fields without hand-writing JSON. |
| US-02 | **As a** user, **I want** a service worker with precache and offline fallback **so that** I can use the installed PWA when the network is unavailable. |
| US-03 | **As an** integrator, **I want** `nowo_pwa_head()`, `nowo_pwa_install_prompt()`, and `nowo_pwa_install_links()` with route targeting **so that** PWA tags appear only on the routes I choose. |
| US-04 | **As an** integrator, **I want** to override Twig templates and translations in my application **so that** markup and copy match my product without forking the bundle. |
| US-05 | **As an** integrator, **I want** a TypeScript client (`pwa.js`) for SW registration, update checks, and install banner **so that** I do not maintain boilerplate in every layout. |
| US-06 | **As a** maintainer, **I want** 100% PHP and TypeScript coverage in CI **so that** manifest builders and client behavior regressions are caught before release. |

**Out of scope for these stories:** push notifications, background sync queues, or app-store submission tooling (see non-goals below).

---

## Bundle functional scope

**Goal:** turn a Symfony application into a **Progressive Web App** with a configurable **Web App Manifest**, **service worker**, **offline page**, **HTML meta tags**, and **install prompt** — no database, no vendor lock-in.

**In scope**

| Area | Responsibility |
| --- | --- |
| `nowo_pwa.manifest` | Build a W3C Web App Manifest (basic + extended fields: shortcuts, protocol handlers, share target, etc.) served at a configurable route. |
| `nowo_pwa.meta` | Inject mobile/Apple/Microsoft meta tags and theme variants via `nowo_pwa_head()`. |
| `nowo_pwa.service_worker` | Generate a dynamic service worker script (`network-first`, `cache-first`, `stale-while-revalidate`) with precache, runtime patterns, deny patterns, and offline URL. |
| `nowo_pwa.install_prompt` | Render a dismissible install banner when `beforeinstallprompt` fires. |
| `nowo_pwa.install_links` | Render install / uninstall links that toggle based on PWA installation state. |
| `nowo_pwa.client` | Pass client options to `pwa.js` (register on load, visibility update checks, reload on update). |
| `nowo_pwa.http` | Set Cache-Control headers on manifest and service worker responses. |
| `nowo_pwa.route_targeting` | Limit head tags and client script to routes (`all` / `only` / `except`). |
| Twig API | `nowo_pwa_head()`, `nowo_pwa_install_prompt()`, `nowo_pwa_install_links()`, `nowo_pwa_enabled()`. |
| Dev tooling | Web Profiler data collector (`nowo_pwa`) when `kernel.debug` is true. |
| Overrides | Templates under `@NowoPwaBundle/pwa/` and translations domain `NowoPwaBundle` can be overridden from the app. |

**Explicit non-goals**

- **Push notifications** or subscription management.
- **Background sync** / periodic sync APIs beyond what the generated service worker provides.
- **Native app wrappers** (Capacitor, Tauri, etc.).
- **Guaranteeing** installability on every browser (install criteria remain platform-defined).

**Demos** (`demo/symfony8`, FrankenPHP on port **8025**) illustrate integration; they are **not** part of the Packagist package API — the contract for consumers is the extension `nowo_pwa`, Twig helpers, routes, and published assets.

---

## Validating the functional spec

- Run **`make release-check`** (or **`composer qa`** + **`make test-ts`** + **`make test-coverage-100`**): coding standard, Rector dry-run, PHPStan, translations validation, PHPUnit at 100% line coverage, Vitest at 100%, demo healthcheck.
- **`make -C demo release-check`**: HTTP 200 on `/`, valid manifest JSON, and `/sw.js` containing the bundle cache prefix.
- New or changed behavior must add or adjust **tests** under `tests/` (PHP) and `src/Resources/assets/src/*.test.ts` (TypeScript) rather than relying on prose alone.

---

## Requirement identifiers (`REQ-*`)

| ID | Where | What it marks |
| --- | --- | --- |
| `REQ-MAKE-001` | Root [`Makefile`](../Makefile) | Docker workflow, `ensure-up`, `release-check` |
| `REQ-MAKE-002` | Root [`Makefile`](../Makefile) | Full `release-check` chain (CS, PHPStan, coverage, demos, TS) |
| `REQ-MAKE-004` | Root [`Makefile`](../Makefile) | `validate-translations` for `NowoPwaBundle` domain |
| `REQ-MAKE-008` | Root [`Makefile`](../Makefile), [`demo/Makefile`](../demo/Makefile) | `update-deps` across bundle and demos |
| `REQ-TEST-001` | `composer test`, `make test` | PHPUnit unit + integration suites |
| `REQ-TEST-006` | `make test-coverage-100` | 100% PHP line coverage threshold |
| `REQ-TEST-009` | `make test-ts`, CI job **TypeScript (Vitest)** | Vitest coverage for `pwa-client.ts` |
| `REQ-TEST-010` | CI job **PHPStan** | Static analysis at bundle `phpstan.neon.dist` level |
| `REQ-DEMO-002` | [`docs/DEMO-FRANKENPHP.md`](DEMO-FRANKENPHP.md) | FrankenPHP demo stack |
| `REQ-DEMO-005` | [`demo/symfony8/Makefile`](../demo/symfony8/Makefile) | Canonical `make up` → `Demo started at: http://localhost:<PORT>` |
| `REQ-DEMO-007` | [`demo/symfony8/Makefile`](../demo/symfony8/Makefile) | `update-bundle`: sync mounted bundle, autoload, cache |
| `REQ-TWIG-001` | [`docs/USAGE.md`](USAGE.md) | Override path `templates/bundles/NowoPwaBundle/` |
| `REQ-I18N-001` | [`docs/USAGE.md`](USAGE.md) | Translation domain `NowoPwaBundle` |

When you change scripted behavior, **update the existing `REQ-*` comment** if the ID still describes the rule, or **introduce a new `REQ-*`** and reference it from the PR description and affected docs.

---

## Suggested workflow for contributors

1. **Clarify behavior** in an issue or draft PR: acceptance criteria for the **bundle** (functional spec) and, if relevant, for **Makefiles/demos** (`REQ-*`).
2. **Implement** with PHPUnit/Vitest tests and PHPStan; keep coverage at 100% for `src/` (excluding documented exclusions in `phpunit.xml.dist`).
3. **Anchor scripts and demos** when dev UX changes: add or adjust `REQ-*` comments in Makefiles.
4. **Ship docs** when behavior or configuration changes: [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`CHANGELOG.md`](CHANGELOG.md), and [`UPGRADING.md`](UPGRADING.md) when consumers must change code or config.
5. **Keep Spec Kit artifacts in sync** when production code under `src/` changes:
   - Update [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) and [`code-inventory.md`](../specs/001-baseline/code-inventory.md).
   - Follow the maintainer checklist in [`SPEC-KIT.md`](SPEC-KIT.md).
   - For **new features**, use Cursor Agent skills (`/speckit-specify`, `/speckit-plan`, `/speckit-tasks`) as documented in SPEC-KIT.

---


## GitHub Spec Kit (summary)

This repository uses [GitHub Spec Kit](https://github.com/github/spec-kit) with **Cursor Agent** (`cursor-agent` integration).

| Artifact | Path |
| --- | --- |
| **Operator manual** (install, init, usage) | [`SPEC-KIT.md`](SPEC-KIT.md) |
| Baseline spec | [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) |
| Code inventory (100%) | [`specs/001-baseline/code-inventory.md`](../specs/001-baseline/code-inventory.md) |
| Constitution | [`.specify/memory/constitution.md`](../.specify/memory/constitution.md) |
| Cursor Agent skills | [`.cursor/skills/`](../.cursor/skills/) (`speckit-*`) |

**Quick start (maintainers):**

```bash
# Install Specify CLI (once per machine) — see SPEC-KIT.md
specify init --here --force --integration cursor-agent --script sh
specify integration list   # Cursor → installed (default)
```

In Cursor Agent, start a new feature with `/speckit-specify <description>`. For day-to-day tooling details, skills reference, folder layout, and troubleshooting, read **[`SPEC-KIT.md`](SPEC-KIT.md)**.

---

## Relationship to Engram / external checklists

[`ENGRAM.md`](ENGRAM.md) covers Nowo-wide documentation checklist items and MCP setup. This document ties together **what the bundle does**, **how we verify it**, and **local `REQ-*` habits**. Both coexist: Engram for org-level compliance, this file for product + traceability expectations.

---

## See also

- [`SPEC-KIT.md`](SPEC-KIT.md) — GitHub Spec Kit manual (install, structure, usage)
- [`USAGE.md`](USAGE.md)
- [`CONFIGURATION.md`](CONFIGURATION.md)
- [`CONTRIBUTING.md`](CONTRIBUTING.md)
- [`RELEASE.md`](RELEASE.md)
- [`DEMO-FRANKENPHP.md`](DEMO-FRANKENPHP.md)
