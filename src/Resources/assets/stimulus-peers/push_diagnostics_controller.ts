import { Controller } from "@hotwired/stimulus";
import {
  DEFAULT_CSRF_HEADER,
  PUSH_CHECK_ORDER,
  runPushChecks,
  type PushCheck,
  type PushCheckReport,
  type PushConfig,
} from "../src/push-checks";

export type DiagnosticsLabels = {
  steps: Record<string, string>;
  codes: Record<string, string>;
  status: Record<string, string>;
  running: string;
  summaryOk: string;
  summaryFail: string;
  skipped: string;
  unexpected: string;
  testSending: string;
  testOk: string;
  testFail: string;
  testNoSubscription: string;
  testError: string;
};

type LabelsInput = Partial<Omit<DiagnosticsLabels, "steps" | "codes" | "status">> & {
  steps?: Record<string, string>;
  codes?: Record<string, string>;
  status?: Record<string, string>;
};

type TestResponse = {
  ok?: boolean;
  error?: string;
  reason?: string;
  results?: { host?: string; ok?: boolean; status?: number | null; reason?: string | null }[];
};

const STATUS_ICONS: Record<PushCheck["status"], string> = {
  ok: "✓",
  warn: "!",
  fail: "✗",
  skip: "–",
};

/**
 * `push-diagnostics` — runs every Web Push step for this device (see `../src/push-checks.ts`),
 * lists what is OK and what is missing, and offers a "send test notification" round-trip
 * against the bundle endpoint (`nowo_pwa_push_test`) or any host URL with the same JSON contract.
 *
 * Markup: `@NowoPwaBundle/pwa/push_diagnostics.html.twig`. All rendering uses `textContent` (no HTML sinks).
 */
export default class extends Controller<HTMLElement> {
  static targets = ["list", "summary", "testButton", "testResult", "checkButton"];

  static values = {
    configUrl: String,
    vapidPublicKey: String,
    preference: String,
    subscribeUrl: String,
    testUrl: String,
    csrfToken: String,
    csrfHeader: { type: String, default: DEFAULT_CSRF_HEADER },
    auto: Boolean,
    labels: Object,
    classPrefix: { type: String, default: "nowo-pwa-push-check" },
  };

  declare readonly listTarget: HTMLElement;
  declare readonly summaryTarget: HTMLElement;
  declare readonly hasTestButtonTarget: boolean;
  declare readonly testButtonTarget: HTMLButtonElement;
  declare readonly hasTestResultTarget: boolean;
  declare readonly testResultTarget: HTMLElement;
  declare readonly hasCheckButtonTarget: boolean;
  declare readonly checkButtonTarget: HTMLButtonElement;
  declare configUrlValue: string;
  declare vapidPublicKeyValue: string;
  declare preferenceValue: string;
  declare subscribeUrlValue: string;
  declare testUrlValue: string;
  declare csrfTokenValue: string;
  declare csrfHeaderValue: string;
  declare autoValue: boolean;
  declare labelsValue: LabelsInput;
  declare classPrefixValue: string;

  private running = false;

  connect(): void {
    if (this.hasTestButtonTarget && !this.testUrlValue) {
      this.testButtonTarget.hidden = true;
    }
    if (this.autoValue) {
      // No user gesture yet: do not prompt, just report whether permission is still pending.
      void this.run(false);
    }
  }

  /** "Check again" button — may prompt for notification permission. */
  async check(): Promise<void> {
    await this.run(true);
  }

  /** "Send test notification" button. */
  async test(): Promise<void> {
    const labels = this.labels();
    if (!this.testUrlValue) {
      return;
    }
    this.setTestResult(labels.testSending, "running");
    if (this.hasTestButtonTarget) {
      this.testButtonTarget.disabled = true;
    }
    try {
      const headers: Record<string, string> = { Accept: "application/json" };
      if (this.csrfTokenValue) {
        headers[this.csrfHeaderValue || DEFAULT_CSRF_HEADER] = this.csrfTokenValue;
      }
      const response = await fetch(this.testUrlValue, {
        method: "POST",
        credentials: "same-origin",
        headers,
      });
      let payload: TestResponse = {};
      try {
        payload = (await response.json()) as TestResponse;
      } catch {
        // Non-JSON error page.
      }
      if (response.ok && payload.ok) {
        this.setTestResult(labels.testOk, "ok");
        return;
      }
      if (payload.error === "no_subscription") {
        this.setTestResult(labels.testNoSubscription, "fail");
        return;
      }
      const reasons = (payload.results ?? [])
        .filter((row) => !row.ok)
        .map((row) =>
          [row.host, row.status, row.reason].filter((part) => part !== null && part !== undefined && part !== "").join(" · "),
        )
        .join(" | ");
      const reason = reasons || [payload.error, payload.reason].filter(Boolean).join(" · ") || String(response.status);
      this.setTestResult(labels.testFail.replace("%reason%", reason), "fail");
    } catch {
      this.setTestResult(labels.testError, "fail");
    } finally {
      if (this.hasTestButtonTarget) {
        this.testButtonTarget.disabled = false;
      }
    }
  }

  private async run(requestPermission: boolean): Promise<void> {
    if (this.running) {
      return;
    }
    this.running = true;
    const labels = this.labels();
    this.element.dataset.state = "running";
    this.summaryTarget.textContent = labels.running;
    if (this.hasCheckButtonTarget) {
      this.checkButtonTarget.disabled = true;
    }
    try {
      const report = await runPushChecks({
        configUrl: this.configUrlValue || undefined,
        config: this.inlineConfig(),
        subscribeUrl: this.subscribeUrlValue || undefined,
        csrfToken: this.csrfTokenValue || undefined,
        csrfHeader: this.csrfHeaderValue,
        requestPermission,
      });
      this.render(report);
    } finally {
      this.running = false;
      if (this.hasCheckButtonTarget) {
        this.checkButtonTarget.disabled = false;
      }
    }
  }

  /** VAPID key rendered server-side (no config round-trip); undefined when `configUrl` must be fetched. */
  private inlineConfig(): PushConfig | undefined {
    if (!this.vapidPublicKeyValue && this.configUrlValue) {
      return undefined;
    }
    const config: PushConfig = {
      vapidPublicKey: this.vapidPublicKeyValue || null,
      configured: this.vapidPublicKeyValue !== "",
    };
    if (this.preferenceValue === "true" || this.preferenceValue === "false") {
      config.preferenceEnabled = this.preferenceValue === "true";
    }
    return config;
  }

  private render(report: PushCheckReport): void {
    const labels = this.labels();
    this.element.dataset.state = report.ok ? "ok" : "fail";
    this.summaryTarget.textContent = report.ok ? labels.summaryOk : labels.summaryFail;
    if (report.ok) {
      delete document.documentElement.dataset.pushBlocked;
    } else if (report.failed?.id === "browser_subscription") {
      document.documentElement.dataset.pushBlocked = "1";
    }
    if (this.hasTestButtonTarget) {
      this.testButtonTarget.hidden = !report.ok || !this.testUrlValue;
    }

    this.listTarget.replaceChildren(...report.checks.map((check) => this.renderCheck(check, labels)));
  }

  private renderCheck(check: PushCheck, labels: DiagnosticsLabels): HTMLElement {
    const prefix = this.classPrefixValue;
    const item = document.createElement("li");
    item.className = `${prefix} ${prefix}--${check.status}`;
    item.dataset.check = check.id;
    item.dataset.status = check.status;
    item.dataset.code = check.code;

    const icon = document.createElement("span");
    icon.className = `${prefix}__icon`;
    icon.setAttribute("aria-hidden", "true");
    icon.textContent = STATUS_ICONS[check.status];
    item.appendChild(icon);

    const body = document.createElement("span");
    body.className = `${prefix}__body`;

    const title = document.createElement("span");
    title.className = `${prefix}__title`;
    title.textContent = labels.steps[check.id];
    const sr = document.createElement("span");
    sr.className = "sr-only";
    sr.textContent = ` (${labels.status[check.status]})`;
    title.appendChild(sr);
    body.appendChild(title);

    const message = document.createElement("span");
    message.className = `${prefix}__message`;
    message.textContent = this.messageFor(check, labels);
    body.appendChild(message);

    if (check.detail && check.status !== "skip") {
      const detail = document.createElement("code");
      detail.className = `${prefix}__detail`;
      detail.textContent = check.detail;
      body.appendChild(detail);
    }

    item.appendChild(body);

    return item;
  }

  private messageFor(check: PushCheck, labels: DiagnosticsLabels): string {
    if (check.status === "skip") {
      return labels.skipped;
    }
    return (
      labels.codes[`${check.id}.${check.code}`] ??
      labels.codes[`${check.id}.${check.status === "ok" ? "ok" : "default"}`] ??
      labels.unexpected
    );
  }

  private setTestResult(text: string, state: string): void {
    if (!this.hasTestResultTarget) {
      return;
    }
    this.testResultTarget.textContent = text;
    this.testResultTarget.dataset.state = state;
    this.testResultTarget.hidden = false;
  }

  private labels(): DiagnosticsLabels {
    const raw: LabelsInput = this.labelsValue;
    const steps: Record<string, string> = {};
    for (const id of PUSH_CHECK_ORDER) {
      steps[id] = raw.steps?.[id] ?? id;
    }
    return {
      steps,
      codes: raw.codes ?? {},
      status: { ok: "OK", warn: "Warning", fail: "Missing", skip: "Skipped", ...(raw.status ?? {}) },
      running: raw.running ?? "Checking…",
      summaryOk: raw.summaryOk ?? "Everything is ready.",
      summaryFail: raw.summaryFail ?? "Push is not active on this device.",
      skipped: raw.skipped ?? "Not checked.",
      unexpected: raw.unexpected ?? "Unexpected error.",
      testSending: raw.testSending ?? "Sending…",
      testOk: raw.testOk ?? "Test sent.",
      testFail: raw.testFail ?? "The push service rejected the test: %reason%",
      testNoSubscription: raw.testNoSubscription ?? "No subscription saved yet.",
      testError: raw.testError ?? "Could not send the test.",
    };
  }
}
