import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PushDiagnosticsController from './push_diagnostics_controller';

const VAPID = btoa(String.fromCharCode(...Array.from({ length: 65 }, (_, i) => i % 256)))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/g, '');

const LABELS = {
    steps: { browser_subscription: 'Browser subscription' },
    codes: {
        'support.ok': 'Supported',
        'browser_subscription.push_service_error': 'Enable Google push messaging',
        'browser_subscription.created': 'Created',
        'server_config.ok': 'Server OK',
    },
    status: { fail: 'Missing!' },
    summaryOk: 'All good',
    summaryFail: 'Push inactive',
    skipped: 'Not checked',
    testOk: 'Test sent',
    testFail: 'Rejected: %reason%',
    testNoSubscription: 'No subscription',
};

type MountOptions = {
    auto?: boolean;
    attrs?: Record<string, string>;
    labels?: object | null;
    withTestButton?: boolean;
    withTestResult?: boolean;
    withCheckButton?: boolean;
};

describe('push-diagnostics controller', () => {
    let application: Application | undefined;
    let fetchMock: ReturnType<typeof vi.fn>;
    let subscribe: ReturnType<typeof vi.fn>;

    const mount = async ({
        auto = false,
        attrs = {},
        labels = LABELS,
        withTestButton = true,
        withTestResult = true,
        withCheckButton = true,
    }: MountOptions = {}): Promise<PushDiagnosticsController> => {
        const values: Record<string, string> = {
            'config-url': '/config',
            'subscribe-url': '/subscribe',
            'test-url': '/test',
            'csrf-token': 'csrf',
            auto: String(auto),
            ...attrs,
        };
        const section = document.createElement('section');
        section.dataset.controller = 'push-diagnostics';
        for (const [name, value] of Object.entries(values)) {
            section.setAttribute(`data-push-diagnostics-${name}-value`, value);
        }
        if (labels !== null) {
            section.setAttribute('data-push-diagnostics-labels-value', JSON.stringify(labels));
        }
        section.innerHTML = `
            <p data-push-diagnostics-target="summary"></p>
            <ul data-push-diagnostics-target="list"></ul>
            ${withCheckButton ? '<button data-push-diagnostics-target="checkButton"></button>' : ''}
            ${withTestButton ? '<button data-push-diagnostics-target="testButton" hidden></button>' : ''}
            ${withTestResult ? '<p data-push-diagnostics-target="testResult" hidden></p>' : ''}
        `;
        document.body.appendChild(section);
        application = Application.start();
        application.register('push-diagnostics', PushDiagnosticsController);
        await Promise.resolve();
        return application.getControllerForElementAndIdentifier(section, 'push-diagnostics') as PushDiagnosticsController;
    };

    const testButton = (): HTMLButtonElement => document.querySelector('[data-push-diagnostics-target="testButton"]') as HTMLButtonElement;
    const testResult = (): HTMLElement => document.querySelector('[data-push-diagnostics-target="testResult"]') as HTMLElement;

    beforeEach(() => {
        subscribe = vi.fn().mockResolvedValue({
            endpoint: 'https://fcm.googleapis.com/fcm/send/1',
            options: { applicationServerKey: null },
            toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/fcm/send/1' }),
        });
        Object.defineProperty(navigator, 'serviceWorker', {
            configurable: true,
            value: {
                ready: Promise.resolve({
                    scope: 'https://app.test/',
                    pushManager: { getSubscription: vi.fn().mockResolvedValue(null), subscribe },
                }),
            },
        });
        Object.defineProperty(window, 'PushManager', { configurable: true, value: function PushManager() {} });
        vi.stubGlobal('Notification', { permission: 'granted', requestPermission: vi.fn() });
        fetchMock = vi.fn(async (url: string) => {
            if (url === '/config') {
                return { ok: true, status: 200, json: async () => ({ push: { preferenceEnabled: true, configured: true, vapidPublicKey: VAPID } }) };
            }
            return { ok: true, status: 200, json: async () => ({ ok: true }) };
        });
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        application?.stop();
        application = undefined;
        document.body.innerHTML = '';
        delete document.documentElement.dataset.pushBlocked;
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        Reflect.deleteProperty(navigator, 'serviceWorker');
        Reflect.deleteProperty(window, 'PushManager');
    });

    it('auto-runs on connect and lists every step as OK with the default class prefix', async () => {
        document.documentElement.dataset.pushBlocked = '1';
        const controller = await mount({ auto: true });
        await vi.waitFor(() => expect(controller.element.getAttribute('data-state')).toBe('ok'));

        const items = Array.from(document.querySelectorAll<HTMLElement>('li.nowo-pwa-push-check'));
        expect(items).toHaveLength(7);
        expect(items.every((li) => li.dataset.status === 'ok')).toBe(true);
        expect(items[0].classList.contains('nowo-pwa-push-check--ok')).toBe(true);
        expect(document.querySelector('[data-push-diagnostics-target="summary"]')?.textContent).toBe('All good');
        expect(items[0].querySelector('.nowo-pwa-push-check__message')?.textContent).toBe('Supported');
        // Fallback to "<step>.ok" message, then the generic "unexpected" label.
        expect(items[1].querySelector('.nowo-pwa-push-check__message')?.textContent).toBe('Server OK');
        expect(items[2].querySelector('.nowo-pwa-push-check__message')?.textContent).toBe('Unexpected error.');
        expect(items[2].querySelector('.nowo-pwa-push-check__title')?.textContent).toBe('preference (OK)');
        expect(testButton().hidden).toBe(false);
        expect(document.documentElement.dataset.pushBlocked).toBeUndefined();
    });

    it('uses a custom class prefix and the inline VAPID key (no config fetch, no server step)', async () => {
        const controller = await mount({
            attrs: { 'config-url': '', 'subscribe-url': '', 'vapid-public-key': VAPID, preference: 'false', 'class-prefix': 'push-check' },
        });
        await controller.check();

        expect(fetchMock).not.toHaveBeenCalled();
        const failed = document.querySelector<HTMLElement>('li.push-check[data-check="preference"]');
        expect(failed?.dataset.code).toBe('preference_disabled');
        expect(failed?.querySelector('.push-check__title')?.textContent).toBe('preference (Missing!)');
        expect(document.querySelector('li[data-check="server_subscription"]')).toBeNull();
    });

    it('accepts preference "true" and reports a missing VAPID key without any config source', async () => {
        const ok = await mount({ attrs: { 'config-url': '', 'subscribe-url': '', 'vapid-public-key': VAPID, preference: 'true' } });
        await ok.check();
        expect(ok.element.getAttribute('data-state')).toBe('ok');
        application?.stop();
        document.body.innerHTML = '';

        const missing = await mount({ attrs: { 'config-url': '' } });
        await missing.check();
        expect(document.querySelector<HTMLElement>('li[data-check="server_config"]')?.dataset.code).toBe('vapid_missing');
        expect(document.querySelector<HTMLElement>('li[data-check="server_config"] .nowo-pwa-push-check__message')?.textContent).toBe('Unexpected error.');
    });

    it('shows what is missing when the push service rejects the subscription', async () => {
        subscribe.mockRejectedValue(new DOMException('Registration failed - push service error', 'AbortError'));
        const controller = await mount();
        expect(fetchMock).not.toHaveBeenCalled();

        await controller.check();

        expect(controller.element.getAttribute('data-state')).toBe('fail');
        const failed = document.querySelector<HTMLElement>('li[data-check="browser_subscription"]');
        expect(failed?.dataset.status).toBe('fail');
        expect(failed?.querySelector('.nowo-pwa-push-check__title')?.textContent).toContain('Browser subscription');
        expect(failed?.querySelector('.nowo-pwa-push-check__message')?.textContent).toBe('Enable Google push messaging');
        expect(failed?.querySelector('.nowo-pwa-push-check__detail')?.textContent).toContain('AbortError');
        const skipped = document.querySelector<HTMLElement>('li[data-check="server_subscription"]');
        expect(skipped?.dataset.status).toBe('skip');
        expect(skipped?.querySelector('.nowo-pwa-push-check__message')?.textContent).toBe('Not checked');
        expect(document.documentElement.dataset.pushBlocked).toBe('1');
        expect(testButton().hidden).toBe(true);
    });

    it('does not flag push as blocked for other failures and ignores concurrent runs', async () => {
        vi.stubGlobal('Notification', { permission: 'denied', requestPermission: vi.fn() });
        const controller = await mount({ withCheckButton: false });
        await Promise.all([controller.check(), controller.check()]);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(document.documentElement.dataset.pushBlocked).toBeUndefined();
        expect(document.querySelector('[data-push-diagnostics-target="summary"]')?.textContent).toBe('Push inactive');
    });

    it('falls back to English labels when none are given', async () => {
        vi.stubGlobal('Notification', { permission: 'denied', requestPermission: vi.fn() });
        const controller = await mount({ labels: null });
        await controller.check();
        expect(document.querySelector('[data-push-diagnostics-target="summary"]')?.textContent).toBe('Push is not active on this device.');
        expect(document.querySelector('li[data-check="support"] .nowo-pwa-push-check__title')?.textContent).toBe('support (OK)');
        expect(document.querySelector('li[data-check="service_worker"] .nowo-pwa-push-check__message')?.textContent).toBe('Not checked.');

        fetchMock.mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ ok: true }) });
        await controller.test();
        expect(testResult().textContent).toBe('Test sent.');
    });

    it('hides the test button without a test URL', async () => {
        const controller = await mount({ attrs: { 'test-url': '' } });
        expect(testButton().hidden).toBe(true);
        await controller.test();
        expect(fetchMock).not.toHaveBeenCalled();
        await controller.check();
        expect(controller.element.getAttribute('data-state')).toBe('ok');
        expect(testButton().hidden).toBe(true);
    });

    it('reports the test notification outcome', async () => {
        const controller = await mount();

        fetchMock.mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ ok: true }) });
        await controller.test();
        expect(testResult().textContent).toBe('Test sent');
        expect(testResult().dataset.state).toBe('ok');
        expect(testResult().hidden).toBe(false);
        expect(testButton().disabled).toBe(false);
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/test');
        expect((init.headers as Record<string, string>)['X-CSRF-TOKEN']).toBe('csrf');

        fetchMock.mockResolvedValueOnce({ ok: false, status: 404, json: async () => ({ ok: false, error: 'no_subscription' }) });
        await controller.test();
        expect(testResult().textContent).toBe('No subscription');

        fetchMock.mockResolvedValueOnce({
            ok: false,
            status: 502,
            json: async () => ({
                ok: false,
                results: [
                    { host: 'fcm.googleapis.com', ok: false, status: 403, reason: 'Forbidden' },
                    { host: 'web.push.apple.com', ok: true, status: 201, reason: null },
                ],
            }),
        });
        await controller.test();
        expect(testResult().textContent).toBe('Rejected: fcm.googleapis.com · 403 · Forbidden');
        expect(testResult().dataset.state).toBe('fail');

        fetchMock.mockResolvedValueOnce({ ok: false, status: 500, json: async () => ({ ok: false, error: 'client_error', reason: 'PSR-17 missing' }) });
        await controller.test();
        expect(testResult().textContent).toBe('Rejected: client_error · PSR-17 missing');

        fetchMock.mockResolvedValueOnce({
            ok: false,
            status: 503,
            json: async () => {
                throw new Error('html');
            },
        });
        await controller.test();
        expect(testResult().textContent).toBe('Rejected: 503');

        fetchMock.mockRejectedValueOnce(new TypeError('offline'));
        await controller.test();
        expect(testResult().textContent).toBe('Could not send the test.');
    });

    it('sends the test without CSRF header / buttons / result target when absent', async () => {
        const controller = await mount({ attrs: { 'csrf-token': '', 'csrf-header': '' }, withTestButton: false, withTestResult: false });
        fetchMock.mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ ok: true }) });
        await controller.test();
        const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(init.headers).toEqual({ Accept: 'application/json' });
        await controller.check();
        expect(controller.element.getAttribute('data-state')).toBe('ok');
    });

    it('uses a custom CSRF header (and the default one when emptied)', async () => {
        const controller = await mount({ attrs: { 'csrf-header': 'X-Token' } });
        fetchMock.mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ ok: true }) });
        await controller.test();
        expect((fetchMock.mock.calls[0][1] as RequestInit).headers).toMatchObject({ 'X-Token': 'csrf' });

        controller.element.setAttribute('data-push-diagnostics-csrf-header-value', '');
        fetchMock.mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ ok: true }) });
        await controller.test();
        expect((fetchMock.mock.calls[1][1] as RequestInit).headers).toMatchObject({ 'X-CSRF-TOKEN': 'csrf' });
    });
});
