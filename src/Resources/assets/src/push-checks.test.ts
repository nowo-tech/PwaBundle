import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    applicableChecks,
    describeError,
    endpointHost,
    PUSH_CHECK_ORDER,
    runPushChecks,
    sameKey,
    urlBase64ToUint8Array,
    type PushCheckOptions,
    type PushConfig,
} from './push-checks';

const VAPID = btoa(String.fromCharCode(...Array.from({ length: 65 }, (_, i) => i % 256)))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/g, '');

const config = (overrides: Partial<PushConfig> = {}): PushConfig => ({
    preferenceEnabled: true,
    vapidPublicKey: VAPID,
    configured: true,
    ...overrides,
});

type FakeSubscription = {
    endpoint: string;
    options: { applicationServerKey: ArrayBuffer | null };
    toJSON: () => object;
    unsubscribe: ReturnType<typeof vi.fn>;
};

const fakeSubscription = (key: Uint8Array | null = urlBase64ToUint8Array(VAPID), endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): FakeSubscription => ({
    endpoint,
    options: { applicationServerKey: key ? key.slice().buffer : null },
    toJSON: () => ({ endpoint, keys: { p256dh: 'p', auth: 'a' } }),
    unsubscribe: vi.fn().mockResolvedValue(true),
});

describe('runPushChecks', () => {
    let fetchMock: ReturnType<typeof vi.fn>;
    let pushManager: { getSubscription: ReturnType<typeof vi.fn>; subscribe: ReturnType<typeof vi.fn> };

    const installBrowser = (permission: NotificationPermission = 'granted', ready?: Promise<unknown>): void => {
        pushManager = {
            getSubscription: vi.fn().mockResolvedValue(null),
            subscribe: vi.fn().mockResolvedValue(fakeSubscription()),
        };
        Object.defineProperty(navigator, 'serviceWorker', {
            configurable: true,
            value: { ready: ready ?? Promise.resolve({ scope: 'https://app.test/', pushManager }) },
        });
        Object.defineProperty(window, 'PushManager', { configurable: true, value: function PushManager() {} });
        vi.stubGlobal('Notification', { permission, requestPermission: vi.fn().mockResolvedValue('granted') });
    };

    const options = (extra: Partial<PushCheckOptions> = {}): PushCheckOptions => ({
        configUrl: '/config',
        subscribeUrl: '/subscribe',
        csrfToken: 'csrf',
        requestPermission: true,
        ...extra,
    });

    beforeEach(() => {
        fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({ ok: true }) });
        vi.stubGlobal('fetch', fetchMock);
        installBrowser();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        Reflect.deleteProperty(navigator, 'serviceWorker');
        Reflect.deleteProperty(window, 'PushManager');
        Reflect.deleteProperty(window, 'isSecureContext');
    });

    it('passes every step and posts the new subscription with the CSRF header', async () => {
        fetchMock.mockImplementation(async (url: string) =>
            url === '/config'
                ? { ok: true, status: 200, json: async () => ({ push: config() }) }
                : { ok: true, status: 200, json: async () => ({ ok: true }) },
        );

        const report = await runPushChecks(options());

        expect(report.ok).toBe(true);
        expect(report.failed).toBeNull();
        expect(report.checks.map((c) => c.id)).toEqual(PUSH_CHECK_ORDER);
        expect(report.checks.every((c) => c.status === 'ok')).toBe(true);
        expect(report.checks.find((c) => c.id === 'browser_subscription')).toMatchObject({ code: 'created', detail: 'fcm.googleapis.com' });
        expect(report.checks.find((c) => c.id === 'service_worker')?.detail).toBe('https://app.test/');
        const [, init] = fetchMock.mock.calls.find(([url]) => url === '/subscribe') as [string, RequestInit];
        expect(init.method).toBe('POST');
        expect((init.headers as Record<string, string>)['X-CSRF-TOKEN']).toBe('csrf');
    });

    it('accepts a bare config payload and a custom CSRF header', async () => {
        fetchMock.mockImplementation(async (url: string) =>
            url === '/config'
                ? { ok: true, status: 200, json: async () => ({ vapidPublicKey: VAPID }) }
                : { ok: true, status: 200, json: async () => ({}) },
        );

        const report = await runPushChecks(options({ csrfHeader: 'X-Token' }));

        expect(report.ok).toBe(true);
        expect(report.checks.map((c) => c.id)).not.toContain('preference');
        const [, init] = fetchMock.mock.calls.find(([url]) => url === '/subscribe') as [string, RequestInit];
        expect((init.headers as Record<string, string>)['X-Token']).toBe('csrf');
    });

    it('leaves the optional steps out (inline config, no subscribe URL, no CSRF token)', async () => {
        const report = await runPushChecks({ config: { vapidPublicKey: VAPID }, requestPermission: false });

        expect(report.ok).toBe(true);
        expect(report.checks.map((c) => c.id)).toEqual(['support', 'server_config', 'permission', 'service_worker', 'browser_subscription']);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('omits the CSRF header when no token is given', async () => {
        await runPushChecks(options({ config: config(), csrfToken: undefined }));
        const [, init] = fetchMock.mock.calls.find(([url]) => url === '/subscribe') as [string, RequestInit];
        expect(init.headers as Record<string, string>).not.toHaveProperty('X-CSRF-TOKEN');
    });

    it('flags missing browser support and skips the rest', async () => {
        Reflect.deleteProperty(window, 'PushManager');
        const report = await runPushChecks(options({ config: config() }));
        expect(report.failed).toMatchObject({ id: 'support', code: 'unsupported' });
        expect(report.checks.slice(1).every((c) => c.status === 'skip')).toBe(true);
        expect(report.checks).toHaveLength(7);
    });

    it('flags missing Notification / service worker APIs', async () => {
        vi.stubGlobal('Notification', undefined);
        Reflect.deleteProperty(window, 'Notification');
        expect((await runPushChecks(options({ config: config() }))).failed).toMatchObject({ id: 'support', code: 'unsupported' });

        installBrowser();
        Reflect.deleteProperty(navigator, 'serviceWorker');
        expect((await runPushChecks(options({ config: config() }))).failed).toMatchObject({ id: 'support', code: 'unsupported' });
    });

    it('requires a secure context', async () => {
        Object.defineProperty(window, 'isSecureContext', { configurable: true, value: false });
        const report = await runPushChecks(options({ config: config() }));
        expect(report.failed).toMatchObject({ id: 'support', code: 'insecure_context', detail: window.location.origin });
    });

    it('reports server config problems', async () => {
        fetchMock.mockResolvedValueOnce({ ok: false, status: 500, json: async () => ({}) });
        expect((await runPushChecks(options())).failed).toMatchObject({ id: 'server_config', code: 'http_error', detail: '500' });

        fetchMock.mockRejectedValueOnce(new TypeError('offline'));
        expect((await runPushChecks(options())).failed).toMatchObject({ id: 'server_config', code: 'network', detail: 'TypeError: offline' });

        fetchMock.mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({}) });
        expect((await runPushChecks(options())).failed).toMatchObject({ id: 'server_config', code: 'invalid_response' });

        fetchMock.mockResolvedValueOnce({ ok: true, status: 200, json: async () => null });
        expect((await runPushChecks(options())).failed).toMatchObject({ id: 'server_config', code: 'invalid_response' });

        fetchMock.mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: async () => {
                throw new SyntaxError('bad json');
            },
        });
        expect((await runPushChecks(options())).failed).toMatchObject({ id: 'server_config', code: 'invalid_response', detail: 'SyntaxError: bad json' });

        expect((await runPushChecks(options({ config: config({ configured: false }) }))).failed).toMatchObject({ id: 'server_config', code: 'vapid_missing' });
        expect((await runPushChecks(options({ config: config({ vapidPublicKey: null }) }))).failed).toMatchObject({ id: 'server_config', code: 'vapid_missing' });

        const noSource = await runPushChecks(options({ configUrl: undefined }));
        expect(noSource.failed).toMatchObject({ id: 'server_config', code: 'vapid_missing' });
        // Config unknown: the optional preference step is not listed as skipped.
        expect(noSource.checks.map((c) => c.id)).not.toContain('preference');
    });

    it('reports preference off and permission problems', async () => {
        expect((await runPushChecks(options({ config: config({ preferenceEnabled: false }) }))).failed).toMatchObject({
            id: 'preference',
            code: 'preference_disabled',
        });

        installBrowser('denied');
        expect((await runPushChecks(options({ config: config() }))).failed).toMatchObject({ id: 'permission', code: 'denied' });

        installBrowser('default');
        const pending = await runPushChecks(options({ config: config(), requestPermission: false }));
        expect(pending.failed).toMatchObject({ id: 'permission', code: 'not_granted', detail: 'default' });
        expect(Notification.requestPermission).not.toHaveBeenCalled();

        const prompted = await runPushChecks(options({ config: config() }));
        expect(Notification.requestPermission).toHaveBeenCalled();
        expect(prompted.ok).toBe(true);
    });

    it('marks unexpected errors on the current step', async () => {
        vi.stubGlobal('Notification', { permission: 'default', requestPermission: vi.fn().mockRejectedValue('boom') });
        const report = await runPushChecks(options({ config: config() }));
        expect(report.failed).toMatchObject({ id: 'permission', code: 'unexpected', detail: 'boom' });
        expect(report.checks.filter((c) => c.status === 'skip').map((c) => c.id)).toEqual(['service_worker', 'browser_subscription', 'server_subscription']);
    });

    it('times out or fails when the service worker never becomes ready', async () => {
        installBrowser('granted', new Promise(() => {}));
        const report = await runPushChecks(options({ config: config(), serviceWorkerTimeoutMs: 5 }));
        expect(report.failed).toMatchObject({ id: 'service_worker', code: 'not_ready', detail: '5ms' });

        installBrowser('granted', Promise.reject(new Error('sw crashed')));
        expect((await runPushChecks(options({ config: config() }))).failed).toMatchObject({ id: 'service_worker', code: 'error', detail: 'Error: sw crashed' });
    });

    it('maps getSubscription() and subscribe() rejections', async () => {
        pushManager.getSubscription.mockRejectedValueOnce(new Error('denied'));
        expect((await runPushChecks(options({ config: config() }))).failed).toMatchObject({ id: 'browser_subscription', code: 'error' });

        pushManager.subscribe.mockRejectedValueOnce(new DOMException('Registration failed - push service error', 'AbortError'));
        const report = await runPushChecks(options({ config: config() }));
        expect(report.failed).toMatchObject({
            id: 'browser_subscription',
            code: 'push_service_error',
            detail: 'AbortError: Registration failed - push service error',
        });
        expect(fetchMock).not.toHaveBeenCalledWith('/subscribe', expect.anything());

        pushManager.subscribe.mockRejectedValueOnce(new DOMException('nope', 'NotAllowedError'));
        expect((await runPushChecks(options({ config: config() }))).failed).toMatchObject({ code: 'not_allowed' });

        pushManager.subscribe.mockRejectedValueOnce('weird');
        expect((await runPushChecks(options({ config: config() }))).failed).toMatchObject({ code: 'error', detail: 'weird' });
    });

    it('keeps a matching subscription and renews one made with another VAPID key', async () => {
        pushManager.getSubscription.mockResolvedValueOnce(fakeSubscription());
        const kept = await runPushChecks(options({ config: config() }));
        expect(kept.checks.find((c) => c.id === 'browser_subscription')?.code).toBe('existing');
        expect(pushManager.subscribe).not.toHaveBeenCalled();

        const unknownKey = fakeSubscription(null);
        pushManager.getSubscription.mockResolvedValueOnce(unknownKey);
        expect((await runPushChecks(options({ config: config() }))).checks.find((c) => c.id === 'browser_subscription')?.code).toBe('existing');

        const stale = fakeSubscription(new Uint8Array(65).fill(7));
        pushManager.getSubscription.mockResolvedValueOnce(stale);
        const renewed = await runPushChecks(options({ config: config() }));
        expect(stale.unsubscribe).toHaveBeenCalled();
        expect(pushManager.subscribe).toHaveBeenCalled();
        expect(renewed.checks.find((c) => c.id === 'browser_subscription')?.code).toBe('renewed');

        const failingUnsubscribe = fakeSubscription(new Uint8Array(10));
        failingUnsubscribe.unsubscribe.mockRejectedValueOnce(new Error('gone'));
        pushManager.getSubscription.mockResolvedValueOnce(failingUnsubscribe);
        expect((await runPushChecks(options({ config: config() }))).checks.find((c) => c.id === 'browser_subscription')?.code).toBe('renewed');
    });

    it('surfaces the server error code when saving the subscription fails', async () => {
        fetchMock.mockResolvedValueOnce({ ok: false, status: 400, json: async () => ({ ok: false, error: 'preference_disabled' }) });
        const report = await runPushChecks(options({ config: config() }));
        expect(report.failed).toMatchObject({ id: 'server_subscription', code: 'preference_disabled', detail: '400' });

        fetchMock.mockResolvedValueOnce({
            ok: false,
            status: 502,
            json: async () => {
                throw new Error('html');
            },
        });
        expect((await runPushChecks(options({ config: config() }))).failed).toMatchObject({ code: 'http_error', detail: '502' });

        fetchMock.mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ ok: false }) });
        expect((await runPushChecks(options({ config: config() }))).failed).toMatchObject({ code: 'http_error', detail: '200' });

        fetchMock.mockRejectedValueOnce(new TypeError('offline'));
        expect((await runPushChecks(options({ config: config() }))).failed).toMatchObject({ id: 'server_subscription', code: 'network' });
    });
});

describe('push-checks helpers', () => {
    it('lists applicable checks', () => {
        expect(applicableChecks({}, null)).toEqual(['support', 'server_config', 'permission', 'service_worker', 'browser_subscription']);
        expect(applicableChecks({ subscribeUrl: '/s' }, { vapidPublicKey: 'k', preferenceEnabled: false })).toEqual(PUSH_CHECK_ORDER);
    });

    it('compares keys', () => {
        expect(sameKey(null, new Uint8Array(1))).toBe(true);
        expect(sameKey(new Uint8Array([1, 2]).buffer, new Uint8Array([1, 2]))).toBe(true);
        expect(sameKey(new Uint8Array([1, 3]).buffer, new Uint8Array([1, 2]))).toBe(false);
        expect(sameKey(new Uint8Array([1]).buffer, new Uint8Array([1, 2]))).toBe(false);
    });

    it('extracts endpoint hosts', () => {
        expect(endpointHost('https://updates.push.services.mozilla.com/wpush/v2/x')).toBe('updates.push.services.mozilla.com');
        expect(endpointHost('not a url')).toBe('');
    });

    it('describes errors', () => {
        expect(describeError(new Error('msg'))).toBe('Error: msg');
        const nameless = new Error('only message');
        nameless.name = '';
        expect(describeError(nameless)).toBe('only message');
        expect(describeError(new DOMException('', 'AbortError'))).toBe('AbortError');
        expect(describeError(42)).toBe('42');
    });

    it('decodes URL-safe base64', () => {
        expect(Array.from(urlBase64ToUint8Array('AQID'))).toEqual([1, 2, 3]);
        expect(Array.from(urlBase64ToUint8Array('-_8'))).toEqual([251, 255]);
    });
});
