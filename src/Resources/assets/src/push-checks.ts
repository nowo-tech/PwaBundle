/**
 * Step-by-step Web Push diagnostics / subscription flow for one browser.
 *
 * Every step reports ok / warn / fail / skip with a stable machine code so the UI can explain
 * exactly what is missing instead of failing silently. Used by the `push-diagnostics` Stimulus
 * peer and reusable by host controllers that silently (re)subscribe on page load.
 *
 * Steps (in order; optional ones are left out when they do not apply):
 *  1. support              — Service Worker + PushManager + Notification API and a secure context (HTTPS).
 *  2. server_config        — the server exposes a VAPID public key.
 *  3. preference           — optional: host account preference (only when the config carries `preferenceEnabled`).
 *  4. permission           — Notification permission granted (optionally prompts).
 *  5. service_worker       — a service worker is active for this origin.
 *  6. browser_subscription — a PushManager subscription exists and matches the server VAPID key (renewed otherwise).
 *  7. server_subscription  — optional: the subscription is stored on the server (only with `subscribeUrl`).
 */

export type PushCheckId =
  | "support"
  | "server_config"
  | "preference"
  | "permission"
  | "service_worker"
  | "browser_subscription"
  | "server_subscription";

export type PushCheckStatus = "ok" | "warn" | "fail" | "skip";

export type PushCheck = {
  id: PushCheckId;
  status: PushCheckStatus;
  /** Stable reason code (translated by the caller). */
  code: string;
  /** Raw technical detail (error name/message, HTTP status, scope…). */
  detail?: string;
};

export type PushConfig = {
  /** URL-safe base64 VAPID public key; null/empty when the server has no keys. */
  vapidPublicKey: string | null;
  /** Explicit server flag; `false` fails `server_config` even when a key is present. */
  configured?: boolean;
  /** Host account preference; the `preference` step only runs when this is a boolean. */
  preferenceEnabled?: boolean;
  subscriptionCount?: number;
};

export type PushCheckOptions = {
  /** JSON endpoint returning `{ push: PushConfig }` or a bare `PushConfig` (ignored when `config` is given). */
  configUrl?: string;
  /** Config already known by the caller (e.g. VAPID key rendered by Twig) — skips the GET. */
  config?: PushConfig;
  /** Endpoint that stores `PushSubscription.toJSON()` (POST JSON). Without it the server step is left out. */
  subscribeUrl?: string;
  csrfToken?: string;
  /** Header carrying `csrfToken` (default `X-CSRF-TOKEN`). */
  csrfHeader?: string;
  /** Ask for Notification permission when still "default" (needs a user gesture in some browsers). */
  requestPermission: boolean;
  serviceWorkerTimeoutMs?: number;
};

export type PushCheckReport = {
  ok: boolean;
  checks: PushCheck[];
  /** First failing step, if any. */
  failed: PushCheck | null;
};

export const PUSH_CHECK_ORDER: PushCheckId[] = [
  "support",
  "server_config",
  "preference",
  "permission",
  "service_worker",
  "browser_subscription",
  "server_subscription",
];

export const DEFAULT_CSRF_HEADER = "X-CSRF-TOKEN";

const DEFAULT_SW_TIMEOUT_MS = 8000;

type Fail = (id: PushCheckId, code: string, detail?: string) => never;

class StepFailure extends Error {
  constructor(readonly check: PushCheck) {
    super(check.code);
  }
}

const fail: Fail = (id, code, detail) => {
  throw new StepFailure({ id, status: "fail", code, detail });
};

/**
 * Steps that apply for the given options/config (optional steps are dropped).
 * Before the config is known, `preference` is kept only when `options.config` declares it.
 */
export function applicableChecks(options: Pick<PushCheckOptions, "subscribeUrl">, config?: PushConfig | null): PushCheckId[] {
  return PUSH_CHECK_ORDER.filter((id) => {
    if (id === "preference") {
      return typeof config?.preferenceEnabled === "boolean";
    }
    if (id === "server_subscription") {
      return Boolean(options.subscribeUrl);
    }
    return true;
  });
}

/** Runs every step in order; stops at the first failure and marks the remaining steps as skipped. */
export async function runPushChecks(options: PushCheckOptions): Promise<PushCheckReport> {
  const checks: PushCheck[] = [];
  let config: PushConfig | null = options.config ?? null;
  const pass = (check: PushCheck): void => {
    checks.push(check);
  };

  try {
    // 1. Browser support + secure context.
    if (!("serviceWorker" in navigator) || !("PushManager" in window) || !("Notification" in window)) {
      fail("support", "unsupported");
    }
    if (window.isSecureContext === false) {
      fail("support", "insecure_context", window.location.origin);
    }
    pass({ id: "support", status: "ok", code: "ok" });

    // 2. Server config (VAPID).
    config = config ?? (await fetchConfig(options.configUrl));
    if (config.configured === false || !config.vapidPublicKey) {
      fail("server_config", "vapid_missing");
    }
    pass({ id: "server_config", status: "ok", code: "ok" });

    // 3. Optional host account preference.
    if (typeof config.preferenceEnabled === "boolean") {
      if (!config.preferenceEnabled) {
        fail("preference", "preference_disabled");
      }
      pass({ id: "preference", status: "ok", code: "ok" });
    }

    // 4. Notification permission.
    let permission = Notification.permission;
    if (permission === "default" && options.requestPermission) {
      permission = await Notification.requestPermission();
    }
    if (permission === "denied") {
      fail("permission", "denied");
    }
    if (permission !== "granted") {
      fail("permission", "not_granted", permission);
    }
    pass({ id: "permission", status: "ok", code: "ok" });

    // 5. Service worker active and controlling this origin.
    const registration = await waitForServiceWorker(options.serviceWorkerTimeoutMs ?? DEFAULT_SW_TIMEOUT_MS);
    pass({ id: "service_worker", status: "ok", code: "ok", detail: registration.scope });

    // 6. Browser push subscription (re-created when the VAPID key changed).
    const vapidKey = urlBase64ToUint8Array(config.vapidPublicKey as string);
    const { subscription, code } = await ensureBrowserSubscription(registration, vapidKey);
    pass({ id: "browser_subscription", status: "ok", code, detail: endpointHost(subscription.endpoint) });

    // 7. Optional: server stores the subscription.
    if (options.subscribeUrl) {
      await postSubscription(options, options.subscribeUrl, subscription);
      pass({ id: "server_subscription", status: "ok", code: "ok" });
    }
  } catch (error) {
    const steps = applicableChecks(options, config);
    const check =
      error instanceof StepFailure
        ? error.check
        : ({
            id: steps[Math.min(checks.length, steps.length - 1)] as PushCheckId,
            status: "fail",
            code: "unexpected",
            detail: describeError(error),
          } satisfies PushCheck);
    checks.push(check);
    const done = new Set(checks.map((item) => item.id));
    for (const id of steps.slice(steps.indexOf(check.id) + 1)) {
      if (!done.has(id)) {
        checks.push({ id, status: "skip", code: "skipped" });
      }
    }

    return { ok: false, checks, failed: check };
  }

  return { ok: true, checks, failed: null };
}

async function fetchConfig(configUrl: string | undefined): Promise<PushConfig> {
  if (!configUrl) {
    return fail("server_config", "vapid_missing");
  }
  let response: Response;
  try {
    response = await fetch(configUrl, {
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    });
  } catch (error) {
    return fail("server_config", "network", describeError(error));
  }
  if (!response.ok) {
    return fail("server_config", "http_error", String(response.status));
  }
  let payload: unknown;
  try {
    payload = await response.json();
  } catch (error) {
    return fail("server_config", "invalid_response", describeError(error));
  }
  const candidate = isRecord(payload) && isRecord(payload.push) ? payload.push : payload;
  if (!isRecord(candidate) || !("vapidPublicKey" in candidate)) {
    return fail("server_config", "invalid_response");
  }
  return candidate as PushConfig;
}

async function waitForServiceWorker(timeoutMs: number): Promise<ServiceWorkerRegistration> {
  let timer: number | undefined;
  const timeout = new Promise<null>((resolve) => {
    timer = window.setTimeout(() => resolve(null), timeoutMs);
  });
  let registration: ServiceWorkerRegistration | null;
  try {
    registration = await Promise.race([navigator.serviceWorker.ready, timeout]);
  } catch (error) {
    return fail("service_worker", "error", describeError(error));
  } finally {
    window.clearTimeout(timer);
  }
  if (!registration) {
    return fail("service_worker", "not_ready", `${timeoutMs}ms`);
  }
  return registration;
}

async function ensureBrowserSubscription(
  registration: ServiceWorkerRegistration,
  vapidKey: Uint8Array,
): Promise<{ subscription: PushSubscription; code: string }> {
  let code = "existing";
  let subscription: PushSubscription | null;
  try {
    subscription = await registration.pushManager.getSubscription();
  } catch (error) {
    return fail("browser_subscription", "error", describeError(error));
  }

  // A subscription made with another VAPID key is rejected by the push service: renew it.
  if (subscription && !sameKey(subscription.options?.applicationServerKey ?? null, vapidKey)) {
    try {
      await subscription.unsubscribe();
    } catch {
      // Best effort — subscribe() below reports the real problem.
    }
    subscription = null;
    code = "renewed";
  }

  if (!subscription) {
    try {
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: vapidKey as BufferSource,
      });
    } catch (error) {
      // Brave rejects subscribe() with AbortError ("push service error") until
      // brave://settings/privacy → "Use Google services for push messaging" is on.
      const name = error instanceof Error || error instanceof DOMException ? error.name : "";
      return fail(
        "browser_subscription",
        name === "AbortError" ? "push_service_error" : name === "NotAllowedError" ? "not_allowed" : "error",
        describeError(error),
      );
    }
    if (code !== "renewed") {
      code = "created";
    }
  }

  return { subscription, code };
}

async function postSubscription(options: PushCheckOptions, subscribeUrl: string, subscription: PushSubscription): Promise<void> {
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
  };
  if (options.csrfToken) {
    headers[options.csrfHeader || DEFAULT_CSRF_HEADER] = options.csrfToken;
  }
  let response: Response;
  try {
    response = await fetch(subscribeUrl, {
      method: "POST",
      credentials: "same-origin",
      headers,
      body: JSON.stringify(subscription.toJSON()),
    });
  } catch (error) {
    return fail("server_subscription", "network", describeError(error));
  }

  let payload: { ok?: boolean; error?: string } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    // Non-JSON error page — the HTTP status is enough.
  }
  if (!response.ok || payload.ok === false) {
    fail("server_subscription", payload.error ?? "http_error", String(response.status));
  }
}

/** True when the subscription was created with `expected` (or the browser does not expose its key). */
export function sameKey(current: ArrayBuffer | null, expected: Uint8Array): boolean {
  if (!current) {
    // Browsers that do not expose options.applicationServerKey: keep the subscription.
    return true;
  }
  const bytes = new Uint8Array(current);
  if (bytes.length !== expected.length) {
    return false;
  }
  return bytes.every((value, index) => value === expected[index]);
}

export function endpointHost(endpoint: string): string {
  try {
    return new URL(endpoint).host;
  } catch {
    return "";
  }
}

export function describeError(error: unknown): string {
  if (error instanceof Error || error instanceof DOMException) {
    return error.name && error.message ? `${error.name}: ${error.message}` : error.message || error.name;
  }
  return String(error);
}

export function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
  const raw = window.atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i += 1) {
    output[i] = raw.charCodeAt(i);
  }
  return output;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}
