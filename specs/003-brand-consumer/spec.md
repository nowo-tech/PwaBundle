# Feature Specification: Brand from a brand-contracts provider (spec 220 S4)

**Feature Branch**: `feat/brand-s4`  
**Created**: 2026-11-08  
**Status**: Implemented (target v1.15.0)  
**Input**: Spec 220 ("Brand fully configurable from the bundles, per project"), slice S4: PwaBundle reads name,
short name, colours and the `app_icon` from `nowo-tech/brand-contracts` ^1.0 and replaces Beacon's
`AppearanceManifestOverlayProvider`.

**Baseline**: [`../001-baseline/spec.md`](../001-baseline/spec.md)  
**Docs**: [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md#brand-spec-220), [`docs/USAGE.md`](../../docs/USAGE.md#brand-from-a-brand-provider), [`docs/SECURITY.md`](../../docs/SECURITY.md#threat-model)

---

## Summary

An app that stores its brand (InstanceSettingsBundle's brand bridge, or a host class implementing
`Nowo\Brand\Contract\BrandProviderInterface`) gets a manifest and head tags that follow it, without a host overlay. The
`nowo_pwa` configuration stays the fallback, and an app without a provider sees no change at all.

## User Scenarios & Testing

### User Story 1 — No provider, no change (P1)

**Given** an app without a `BrandProviderInterface` service (or with `nowo_pwa.brand: off`), **When** the manifest or a
page with `nowo_pwa_head()` is served, **Then** the body, the status and the headers are byte for byte those of 1.14.1
(no `ETag`, same `Cache-Control`).

### User Story 2 — An administrator renames the app and uploads an icon (P1)

**Given** a brand that is not the default one, with a square PNG `app_icon` of 512 px, **When** the manifest is
fetched, **Then** `name` / `short_name` are the brand's, `theme_color` is the light `primary`, `background_color` the
light `paper`, `icons` are the icon as `any` and `maskable`, the response has an `ETag` that starts with
`Brand::$version`, and a page's manifest link carries `?v=<version>`, its `theme-color` meta tags the light / dark
`paper`.

### User Story 3 — Default brand keeps the configured names (P1)

**Given** a provider with nothing stored (`Brand::$isDefault`), **When** the manifest is fetched, **Then** `name` and
`short_name` stay `manifest.name` / `short_name` (unless `brand.override_name: true`), while colours and icons follow the
brand.

### User Story 4 — Broken database (P2)

**Given** a provider that throws, **When** the manifest or a page is served, **Then** the configuration applies, a
warning is logged and nothing fails.

## Requirements

- **FR-BRAND-001**: `nowo_pwa.brand` node: `mode` (`auto` default, `off`), `override_name` (false); a string is a
  shorthand for `mode`.
- **FR-BRAND-002**: With `mode: auto` and brand-contracts installed, `PwaBrand` and `BrandManifestOverlayProvider` are
  registered; the provider is a `NULL_ON_INVALID` reference. Controller and Twig extension take `PwaBrand` as an
  optional reference.
- **FR-BRAND-003**: The overlay is tagged `nowo_pwa.manifest_overlay_provider` with priority 1024 (before host
  overlays).
- **FR-BRAND-004**: Icons only from a PNG / JPEG / WebP asset with known square dimensions (contract tolerance) of at
  least 192 px and a URL accepted by `BrandAsset::isSafeUrl()`; `maskable` only for the `app_icon` slot itself at
  512 px or more. Otherwise the configured icons stay.
- **FR-BRAND-005**: With a brand, the manifest `ETag` is `"<version>-<sha256(body)[0..16]>"` and `If-None-Match`
  gives `304`.
- **FR-BRAND-006**: Stateless services (no memo, no reset needed); provider errors are caught, logged and ignored.

## Out of scope

- Variant URLs (192 / 512 PNG): the 1.0 contract has no variant URL; icons are declared at their real size.
- The `favicon` slot (UiKit's `nowo_ui_appearance_head()` prints it) and the offline page name.
