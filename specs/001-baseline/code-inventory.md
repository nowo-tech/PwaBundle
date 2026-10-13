# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/pwa-bundle`  
**Last audited**: 2026-10-13 (v1.7.0 push diagnostics — see [`../002-push-diagnostics/spec.md`](../002-push-diagnostics/spec.md))

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
| `Routing/PwaRouteLoader.php` | Dynamic PWA routes (stateless) | FR-ROUTE-001, FR-ROUTE-002 |
| `Controller/PwaController.php` | Manifest / SW / offline HTTP | FR-CTRL-001, FR-CTRL-002, FR-CTRL-003 |
| `EventSubscriber/PwaBootstrapStatelessCookieSubscriber.php` | Strip Set-Cookie on bootstrap | FR-HTTP-001 |
| `Service/ManifestBuilder.php` | Manifest JSON builder | FR-SVC-001 |
| `Service/ServiceWorkerScriptBuilder.php` | SW script generator | FR-SVC-002, FR-SVC-002b |
| `Service/ServiceWorkerCacheDefaults.php` | Default deny cache patterns | FR-SVC-002b |
| `Service/PwaRouteTargeting.php` | Route match evaluation | FR-SVC-003 |
| `Service/ManifestOverlayProviderInterface.php` | Host manifest overlay (v1.6.0) | FR-SVC-001 |
| `DataCollector/PwaDataCollector.php` | Web Profiler panel data | FR-PROF-001 |
| `Twig/PwaTwigExtension.php` | Twig functions | FR-TWIG-002 |
| `Twig/PwaWebPushTwigExtension.php` | `nowo_pwa_web_push()` | 002: FR-PUSH-008 |
| `DependencyInjection/Configuration/WebPushNodeDefinition.php` | `nowo_pwa.web_push` nodes | 002: FR-PUSH-001 |
| `Controller/PushTestController.php` | Test-notification endpoint | 002: FR-PUSH-005, FR-PUSH-006 |
| `WebPush/PushSubscriptionData.php` | Subscription value object | 002: FR-PUSH-004 |
| `WebPush/PushSubscriptionProviderInterface.php` | Host storage bridge | 002: FR-PUSH-004 |
| `WebPush/WebPushSenderInterface.php` | Sender contract | 002: FR-PUSH-002 |
| `WebPush/WebPushDeliveryReport.php` | Delivery result | 002: FR-PUSH-002 |
| `WebPush/MinishlinkWebPushSender.php` | Default sender (minishlink/web-push 11) | 002: FR-PUSH-002, FR-PUSH-003 |
| `WebPush/WebPushEndpointGuard.php` | Anti-SSRF endpoint allow-list | 002: FR-PUSH-007 |
| `WebPush/WebPushRequirements.php` | Compile-time dependency checks | 002: FR-PUSH-003 |
| `WebPush/Exception/InvalidPushEndpointException.php` | Guard failure | 002: FR-PUSH-007 |
| `WebPush/Exception/WebPushDependencyException.php` | Missing dependency | 002: FR-PUSH-003 |
| `WebPush/Exception/PushTestDeniedException.php` | Host refusal (error code) | 002: FR-PUSH-004 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service wiring | FR-DI-001 |
| `Resources/config/data_collector.yaml` | Profiler collector + `kernel.reset` | FR-DI-002, FR-PROF-001 |

## Twig views (`src/Resources/views/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/pwa/head.html.twig` | Head fragment | FR-TWIG-003 |
| `Resources/views/pwa/install_prompt.html.twig` | Install banner/modal | FR-TWIG-004 |
| `Resources/views/pwa/install_links.html.twig` | Install/uninstall links | FR-TWIG-005 |
| `Resources/views/pwa/offline.html.twig` | Offline fallback page | FR-TWIG-006 |
| `Resources/views/Collector/pwa.html.twig` | Profiler panel UI | FR-PROF-002 |
| `Resources/views/Icon/pwa.svg` | Profiler toolbar icon | FR-PROF-003 |
| `Resources/views/pwa/push_diagnostics.html.twig` | Push diagnostics panel | 002: FR-PUSH-010 |
| `Resources/views/pwa/browser_hint.html.twig` | Browser hint banner | 002: FR-PUSH-011 |
| `Resources/views/pwa/browser_guide.html.twig` | Per-browser setup guide | 002: FR-PUSH-011 |

## TypeScript production (`src/Resources/assets/src/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/pwa.ts` | Client entry (SW + install) | FR-ASSET-001 |
| `Resources/assets/src/pwa-client.ts` | Client library API | FR-ASSET-002 |
| `Resources/assets/src/push-checks.ts` | Web Push step checks | 002: FR-PUSH-009 |
| `Resources/assets/src/browser-detect.ts` | Browser family detection | 002: FR-PUSH-011 |
| `Resources/assets/stimulus-peers/push_diagnostics_controller.ts` | Stimulus `push-diagnostics` | 002: FR-PUSH-010 |
| `Resources/assets/stimulus-peers/browser_hint_controller.ts` | Stimulus `browser-hint` | 002: FR-PUSH-011 |

## TypeScript co-located tests (`src/Resources/assets/src/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/pwa-client.test.ts` | Vitest coverage for client | FR-TEST-001 |
| `Resources/assets/src/push-checks.test.ts` | Vitest push checks | 002: SC-PUSH-002 |
| `Resources/assets/stimulus-peers/push_diagnostics_controller.test.ts` | Vitest diagnostics peer | 002: SC-PUSH-002 |
| `Resources/assets/stimulus-peers/browser_hint_controller.test.ts` | Vitest browser hint peer | 002: SC-PUSH-002 |

## Published frontend assets (`src/Resources/public/` + kit SW append)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/public/pwa.js` | Compiled client bundle | FR-ASSET-003, FR-BUILD-001 |
| `Resources/public/pwa.css` | Install prompt + push diagnostics / browser hint styles | FR-ASSET-004, 002: FR-PUSH-010 |
| `Resources/js/web_push_sw_append.js` | Optional kit Web Push SW handlers | FR-SVC-002 |

## Translations (`src/Resources/translations/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoPwaBundle.en.yaml` | `install.*`, `offline.*`, `push_diagnostics.*`, `browser_hint.*` strings | FR-I18N-001, 002: FR-PUSH-012 |
| `Resources/translations/NowoPwaBundle.es.yaml` | Spanish locale | FR-I18N-001 |
| `Resources/translations/NowoPwaBundle.de.yaml` | German locale | FR-I18N-001 |
| `Resources/translations/NowoPwaBundle.fr.yaml` | French locale | FR-I18N-001 |
| `Resources/translations/NowoPwaBundle.it.yaml` | Italian locale | FR-I18N-001 |
| `Resources/translations/NowoPwaBundle.nl.yaml` | Dutch locale | FR-I18N-001 |
| `Resources/translations/NowoPwaBundle.pt.yaml` | Portuguese locale | FR-I18N-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 31 | 31 |
| YAML config | 2 | 2 |
| Twig / SVG views | 9 | 9 |
| TS production | 6 | 6 |
| TS co-located tests | 4 | 4 |
| Public JS/CSS + kit SW append | 3 | 3 |
| Translations | 7 | 7 |
| **Total `src/` artifacts** | **62** | **62** |

Build output `pwa.js` is documented as produced from `pwa.ts`; it is counted as a shipped artifact, not a separate authoring unit.

