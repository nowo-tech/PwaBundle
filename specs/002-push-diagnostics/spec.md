# Feature Specification: Web Push sender, push diagnostics and browser hints

**Feature Branch**: `002-push-diagnostics`  
**Created**: 2026-10-12  
**Status**: Implemented (v1.7.0)  
**Input**: Extract the generic parts of Symfony Beacon v1.47.0 (browser hint) and v1.49.0 (push diagnostics + test notification, `nyholm/psr7` fix) into the bundle.

**Baseline**: [`../001-baseline/spec.md`](../001-baseline/spec.md) (inventory updated in [`../001-baseline/code-inventory.md`](../001-baseline/code-inventory.md))  
**Docs**: [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md#web-push-sender-and-test-notification), [`docs/USAGE.md`](../../docs/USAGE.md#push-diagnostics-test-notification-and-browser-hints), [`docs/SECURITY.md`](../../docs/SECURITY.md#web-push-test-endpoint)

---

## Summary

Hosts that send Web Push kept re-implementing the same pieces: a VAPID sender, an anti-SSRF endpoint check, a "why does push not work on this device?" checklist, a test-notification round-trip and browser-specific help. Production also hit a silent failure: `minishlink/web-push` 11 needs a PSR-17 implementation, and without one every delivery failed at runtime. This feature ships those pieces as **optional, off-by-default** building blocks.

## User Scenarios & Testing

### User Story 1 — Operator sees a missing PSR-17 implementation at deploy time (P1)

**Given** `nowo_pwa.web_push.enabled: true` and no PSR-17 factory installed, **When** the container compiles (`cache:clear`), **Then** the build fails with `WebPushDependencyException` naming `composer require nyholm/psr7`.

### User Story 2 — User checks push on this device (P1)

**Given** the `push-diagnostics` partial on an account page, **When** the page loads (auto mode, no permission prompt) or the user presses "Check again", **Then** each step (support, server config, optional preference, permission, service worker, browser subscription matching the server VAPID key, optional server copy) is listed as OK / missing / not checked with a translated explanation.

### User Story 3 — User sends a test notification (P1)

**Given** `web_push.test_notification.enabled: true` and a host `PushSubscriptionProviderInterface`, **When** the user presses "Send test notification", **Then** `POST nowo_pwa_push_test` (authenticated, role-gated, CSRF-protected) sends one push to each stored subscription (max `max_subscriptions`) and the panel shows success or the per-device push-service answer; gone subscriptions are removed through the provider.

### User Story 4 — Browser-specific help (P2)

**Given** the `browser-hint` partial, **When** it connects, **Then** `<html data-browser>` is set and only the banner for the detected browser (Brave, Chrome, Edge, Firefox, Safari, iOS, Opera, Samsung, other) is shown, optionally linking to the `browser_guide` partial.

## Requirements

- **FR-PUSH-001**: `nowo_pwa.web_push` config node (`enabled` false, `vapid.{public_key,private_key,subject}`, `allowed_endpoint_hosts`, `test_notification.*`) and `routes.push_test` (`/pwa/push/test`, `nowo_pwa_push_test`). `test_notification.enabled` without `enabled` MUST be rejected.
- **FR-PUSH-002**: `WebPushSenderInterface` (`isConfigured()`, `getPublicKey()`, `send()` → `WebPushDeliveryReport`), default `MinishlinkWebPushSender` aliased when enabled; PSR-18/PSR-17/logger services injected when the container has them, discovered otherwise; stateless (client per send).
- **FR-PUSH-003**: Dependencies are **suggested**, not required. When `web_push.enabled`, `WebPushRequirements` MUST verify at compile time minishlink/web-push ≥ 11 and discoverable PSR-17 factories; a missing PSR-18 client MUST surface as `WebPushDependencyException` on send. Test endpoint MUST additionally require security-bundle + security-csrf.
- **FR-PUSH-004**: `PushSubscriptionProviderInterface` (`findSubscriptions(UserInterface)`, `removeSubscription()`), `PushSubscriptionData`, `PushTestDeniedException($errorCode, $statusCode)`.
- **FR-PUSH-005**: `PushTestController` MUST return JSON: 401 `unauthenticated`, 403 `forbidden` / `invalid_csrf`, 503 `push_not_configured`, provider denial code, 404 `no_subscription`, 500 `client_error`, 200/502 with `results[{host, ok, status, reason, expired}]`.
- **FR-PUSH-006**: Test payload `{event: push.test, title, body, tag, url?, icon?, badge?}`; title/body from host translation keys or bundle catalogue; url/icon/badge default to `service_worker.web_push_defaults`.
- **FR-PUSH-007**: `WebPushEndpointGuard` MUST only allow HTTPS, non-IP hosts matching the allow-list (default FCM, Mozilla, Apple, WNS); rejected endpoints are treated as expired.
- **FR-PUSH-008**: Twig `nowo_pwa_web_push()` → `{enabled, configured, vapid_public_key, test_url, csrf_token_id, csrf_token}`.
- **FR-PUSH-009**: `push-checks.ts` `runPushChecks()` — ordered steps, optional `preference` / `server_subscription`, VAPID key match with renewal, first failure stops and later steps are `skip`.
- **FR-PUSH-010**: Stimulus peer `push-diagnostics` + `pwa/push_diagnostics.html.twig` (configurable identifier, URLs, CSRF header, class prefix; `textContent` rendering only).
- **FR-PUSH-011**: `browser-detect.ts`, Stimulus peer `browser-hint`, `pwa/browser_hint.html.twig`, `pwa/browser_guide.html.twig`.
- **FR-PUSH-012**: All new strings under `push_diagnostics.*` / `browser_hint.*` in the 7 bundle locales, with no host-specific wording.

## Success Criteria

- **SC-PUSH-001**: PHPUnit 100% line coverage including a compiled container with the test endpoint wired.
- **SC-PUSH-002**: Vitest 100% (statements/branches/functions/lines) for `push-checks.ts`, `browser-detect.ts` and both peers.
- **SC-PUSH-003**: Existing hosts upgrade without config changes (everything new is opt-in).

## Out of scope

- Subscription storage / subscribe-unsubscribe endpoints (host-owned; the diagnostics accept a host `subscribe_url`).
- Rate limiting of the test endpoint (documented in SECURITY.md).
