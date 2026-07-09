# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/pwa-bundle`  
**Last audited**: 2026-07-07

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. PHPUnit under `tests/` is out of scope unless promoted in the spec.

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `PwaBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/PwaExtension.php` | DI extension + asset prepend | FR-CFG-002, FR-DI-001 |
| `DependencyInjection/Configuration.php` | Config tree root | FR-CFG-001 |
| `DependencyInjection/Configuration/ManifestNodeDefinition.php` | W3C manifest nodes | FR-CFG-003 |
| `DependencyInjection/Configuration/MetaNodeDefinition.php` | HTML meta nodes | FR-CFG-004 |
| `DependencyInjection/Configuration/ServiceWorkerNodeDefinition.php` | Service worker nodes | FR-CFG-005 |
| `DependencyInjection/Configuration/RouteTargetingNodeDefinition.php` | Route targeting nodes | FR-CFG-006 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace paths | FR-TWIG-001, FR-DI-003 |
| `Routing/PwaRouteLoader.php` | Dynamic PWA routes | FR-ROUTE-001 |
| `Controller/PwaController.php` | Manifest / SW / offline HTTP | FR-CTRL-001, FR-CTRL-002, FR-CTRL-003 |
| `Service/ManifestBuilder.php` | Manifest JSON builder | FR-SVC-001 |
| `Service/ServiceWorkerScriptBuilder.php` | SW script generator | FR-SVC-002 |
| `Service/PwaRouteTargeting.php` | Route match evaluation | FR-SVC-003 |
| `DataCollector/PwaDataCollector.php` | Web Profiler panel data | FR-PROF-001 |
| `Twig/PwaTwigExtension.php` | Twig functions | FR-TWIG-002 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service wiring | FR-DI-001 |
| `Resources/config/data_collector.yaml` | Profiler collector tag | FR-DI-002, FR-PROF-001 |

## Twig views (`src/Resources/views/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/pwa/head.html.twig` | Head fragment | FR-TWIG-003 |
| `Resources/views/pwa/install_prompt.html.twig` | Install banner/modal | FR-TWIG-004 |
| `Resources/views/pwa/install_links.html.twig` | Install/uninstall links | FR-TWIG-005 |
| `Resources/views/pwa/offline.html.twig` | Offline fallback page | FR-TWIG-006 |
| `Resources/views/Collector/pwa.html.twig` | Profiler panel UI | FR-PROF-002 |
| `Resources/views/Icon/pwa.svg` | Profiler toolbar icon | FR-PROF-003 |

## TypeScript production (`src/Resources/assets/src/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/pwa.ts` | Client entry (SW + install) | FR-ASSET-001 |
| `Resources/assets/src/pwa-client.ts` | Client library API | FR-ASSET-002 |

## TypeScript co-located tests (`src/Resources/assets/src/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/pwa-client.test.ts` | Vitest coverage for client | FR-TEST-001 |

## Published frontend assets (`src/Resources/public/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/public/pwa.js` | Compiled client bundle | FR-ASSET-003, FR-BUILD-001 |
| `Resources/public/pwa.css` | Install prompt styles | FR-ASSET-004 |

## Translations (`src/Resources/translations/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoPwaBundle.en.yaml` | `install.*`, `offline.*` strings | FR-I18N-001 |
| `Resources/translations/NowoPwaBundle.es.yaml` | Spanish locale | FR-I18N-001 |
| `Resources/translations/NowoPwaBundle.de.yaml` | German locale | FR-I18N-001 |
| `Resources/translations/NowoPwaBundle.fr.yaml` | French locale | FR-I18N-001 |
| `Resources/translations/NowoPwaBundle.it.yaml` | Italian locale | FR-I18N-001 |
| `Resources/translations/NowoPwaBundle.nl.yaml` | Dutch locale | FR-I18N-001 |
| `Resources/translations/NowoPwaBundle.pt.yaml` | Portuguese locale | FR-I18N-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 15 | 15 |
| YAML config | 2 | 2 |
| Twig / SVG views | 6 | 6 |
| TS production | 2 | 2 |
| TS co-located tests | 1 | 1 |
| Public JS/CSS | 2 | 2 |
| Translations | 7 | 7 |
| **Total `src/` artifacts** | **35** | **35** |

Build output `pwa.js` is documented as produced from `pwa.ts`; it is counted as a shipped artifact, not a separate authoring unit.
