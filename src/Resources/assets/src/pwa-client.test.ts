import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    isPwaInstalled,
    isPwaInstalledAsync,
    registerServiceWorker,
    setupInstallLinks,
    setupInstallPrompt,
    shouldHideInstallPrompt,
    storeDismiss,
    storeNeverDismiss,
} from './pwa-client';

function installBannerHtml(): string {
    return `
        <div id="nowo-pwa-install" hidden data-dismiss-key="dismiss" data-dismiss-days="7" data-never-dismiss-key="never">
            <button type="button" data-pwa-install-action="install">Install</button>
            <button type="button" data-pwa-install-action="dismiss-remind">Dismiss</button>
            <button type="button" data-pwa-install-action="dismiss-never">Never</button>
        </div>
    `;
}

function installLinksHtml(): string {
    return `
        <div id="nowo-pwa-install-links" data-uninstall-help="Remove the app">
            <a href="#" hidden data-pwa-install-action="install">Install app</a>
            <a href="#" hidden data-pwa-install-action="uninstall">Uninstall app</a>
        </div>
    `;
}

describe('pwa-client', () => {
    beforeEach(() => {
        localStorage.clear();
        document.body.innerHTML = '';
        vi.restoreAllMocks();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('registerServiceWorker returns null when unsupported', async () => {
        vi.stubGlobal('navigator', {});

        await expect(registerServiceWorker({ serviceWorkerUrl: '/sw.js', scope: '/' })).resolves.toBeNull();
    });

    it('registerServiceWorker returns null for empty url', async () => {
        vi.stubGlobal('navigator', { serviceWorker: { register: vi.fn() } });

        await expect(registerServiceWorker({ serviceWorkerUrl: '', scope: '/' })).resolves.toBeNull();
    });

    it('registerServiceWorker registers successfully', async () => {
        const registration = { scope: '/' } as ServiceWorkerRegistration;
        const register = vi.fn(async () => registration);
        vi.stubGlobal('navigator', { serviceWorker: { register } });

        await expect(registerServiceWorker({ serviceWorkerUrl: '/sw.js', scope: '/' })).resolves.toBe(registration);
        expect(register).toHaveBeenCalledWith('/sw.js', { scope: '/' });
    });

    it('registerServiceWorker swallows registration errors', async () => {
        vi.stubGlobal('navigator', {
            serviceWorker: { register: vi.fn(async () => { throw new Error('fail'); }) },
        });

        await expect(registerServiceWorker({ serviceWorkerUrl: '/sw.js', scope: '/' })).resolves.toBeNull();
    });

    it('setupInstallPrompt exits when banner is missing', () => {
        expect(() => setupInstallPrompt(document)).not.toThrow();
    });

    it('setupInstallPrompt exits when dismiss is still active', () => {
        document.body.innerHTML = '<div id="nowo-pwa-install" data-dismiss-key="k" data-dismiss-days="7"></div>';
        localStorage.setItem('k', String(Date.now()));

        setupInstallPrompt(document);

        expect(localStorage.getItem('k')).not.toBeNull();
    });

    it('setupInstallPrompt shows banner and handles install click', async () => {
        document.body.innerHTML = installBannerHtml();
        setupInstallPrompt(document);

        const prompt = vi.fn(async () => undefined);
        const event = new Event('beforeinstallprompt') as Event & {
            preventDefault(): void;
            prompt(): Promise<void>;
        };
        Object.assign(event, { preventDefault: vi.fn(), prompt });
        window.dispatchEvent(event);

        const banner = document.getElementById('nowo-pwa-install') as HTMLElement;
        expect(banner.hidden).toBe(false);

        const installBtn = banner.querySelector('[data-pwa-install-action="install"]') as HTMLButtonElement;
        installBtn.click();
        await Promise.resolve();

        expect(prompt).toHaveBeenCalled();
        expect(banner.hidden).toBe(true);
    });

    it('setupInstallPrompt ignores install click without deferred prompt', () => {
        document.body.innerHTML = installBannerHtml();
        setupInstallPrompt(document);

        const banner = document.getElementById('nowo-pwa-install') as HTMLElement;
        const installBtn = banner.querySelector('[data-pwa-install-action="install"]') as HTMLButtonElement;
        installBtn.click();

        expect(banner.hidden).toBe(true);
    });

    it('setupInstallPrompt uses default dismiss settings', () => {
        document.body.innerHTML = `
            <div id="nowo-pwa-install">
                <button type="button" data-pwa-install-action="dismiss">Dismiss</button>
            </div>
        `;
        setupInstallPrompt(document);

        const dismissBtn = document.querySelector('[data-pwa-install-action="dismiss"]') as HTMLButtonElement;
        dismissBtn.click();

        expect(localStorage.getItem('nowo_pwa_install_dismissed')).not.toBeNull();
    });

    it('setupInstallPrompt stores dismiss on dismiss-remind click', () => {
        document.body.innerHTML = installBannerHtml();
        setupInstallPrompt(document);

        const banner = document.getElementById('nowo-pwa-install') as HTMLElement;
        const dismissBtn = banner.querySelector('[data-pwa-install-action="dismiss-remind"]') as HTMLButtonElement;
        dismissBtn.click();

        expect(localStorage.getItem('dismiss')).not.toBeNull();
        expect(banner.hidden).toBe(true);
    });

    it('setupInstallPrompt stores never dismiss', () => {
        document.body.innerHTML = installBannerHtml();
        setupInstallPrompt(document);

        const banner = document.getElementById('nowo-pwa-install') as HTMLElement;
        const neverBtn = banner.querySelector('[data-pwa-install-action="dismiss-never"]') as HTMLButtonElement;
        neverBtn.click();

        expect(localStorage.getItem('never')).toBe('1');
        expect(banner.hidden).toBe(true);
    });

    it('setupInstallPrompt hides when never dismiss is set', () => {
        document.body.innerHTML = installBannerHtml();
        localStorage.setItem('never', '1');
        setupInstallPrompt(document);

        window.dispatchEvent(new Event('beforeinstallprompt'));
        expect(document.getElementById('nowo-pwa-install')?.hidden).toBe(true);
    });

    it('shouldHideInstallPrompt covers ttl branches', () => {
        const now = 1_700_000_000_000;

        expect(shouldHideInstallPrompt('missing', 7, 'never', now)).toBe(false);

        localStorage.setItem('never', '1');
        expect(shouldHideInstallPrompt('missing', 7, 'never', now)).toBe(true);

        localStorage.removeItem('never');
        localStorage.setItem('bad', 'not-a-number');
        expect(shouldHideInstallPrompt('bad', 7, 'never', now)).toBe(false);

        localStorage.setItem('fresh', String(now - 1_000));
        expect(shouldHideInstallPrompt('fresh', 7, 'never', now)).toBe(true);

        localStorage.setItem('expired', String(now - 8 * 24 * 60 * 60 * 1000));
        expect(shouldHideInstallPrompt('expired', 7, 'never', now)).toBe(false);
    });

    it('storeNeverDismiss stores permanent flag', () => {
        storeNeverDismiss('never-key');
        expect(localStorage.getItem('never-key')).toBe('1');
    });

    it('storeDismiss removes key when dismiss days is zero', () => {
        localStorage.setItem('k', '1');
        storeDismiss('k', 0, 123);
        expect(localStorage.getItem('k')).toBeNull();
    });

    it('storeDismiss stores timestamp', () => {
        storeDismiss('k', 3, 456);
        expect(localStorage.getItem('k')).toBe('456');
    });

    it('setupInstallPrompt skips desktop when visibility is mobile', () => {
        Object.defineProperty(navigator, 'userAgent', {
            value: 'Mozilla/5.0 (Windows NT 10.0)',
            configurable: true,
        });
        document.body.innerHTML = `
            <div id="nowo-pwa-install" hidden data-visibility="mobile">
                <button type="button" data-pwa-install-action="install">Install</button>
            </div>
        `;
        setupInstallPrompt(document);
        window.dispatchEvent(new Event('beforeinstallprompt'));
        expect(document.getElementById('nowo-pwa-install')?.hidden).toBe(true);
    });

    it('setupInstallPrompt respects delay before showing banner', () => {
        vi.useFakeTimers();
        document.body.innerHTML = installBannerHtml().replace('hidden', 'hidden data-delay-ms="500"');
        setupInstallPrompt(document);

        const prompt = vi.fn(async () => undefined);
        const event = Object.assign(new Event('beforeinstallprompt'), {
            preventDefault: vi.fn(),
            prompt,
        });
        window.dispatchEvent(event);

        const banner = document.getElementById('nowo-pwa-install') as HTMLElement;
        expect(banner.hidden).toBe(true);
        vi.advanceTimersByTime(500);
        expect(banner.hidden).toBe(false);
        vi.useRealTimers();
    });

    it('setupInstallPrompt opens modal body lock', () => {
        document.body.innerHTML = installBannerHtml().replace(
            'id="nowo-pwa-install"',
            'id="nowo-pwa-install" data-display="modal"',
        );
        setupInstallPrompt(document);

        window.dispatchEvent(new Event('beforeinstallprompt'));
        expect(document.body.classList.contains('nowo-pwa-install-modal-open')).toBe(true);

        const dismissBtn = document.querySelector('[data-pwa-install-action="dismiss-remind"]') as HTMLButtonElement;
        dismissBtn.click();
        expect(document.body.classList.contains('nowo-pwa-install-modal-open')).toBe(false);
    });

    it('setupInstallPrompt skips mobile when visibility is desktop', () => {
        Object.defineProperty(navigator, 'userAgent', {
            value: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
            configurable: true,
        });
        document.body.innerHTML = `
            <div id="nowo-pwa-install" hidden data-visibility="desktop"></div>
        `;
        setupInstallPrompt(document);
        expect(document.getElementById('nowo-pwa-install')?.hidden).toBe(true);
    });

    it('registerServiceWorker checks updates when page becomes visible', async () => {
        const update = vi.fn(async () => undefined);
        const register = vi.fn(async () => ({ update }));
        let visibilityHandler: (() => void) | undefined;
        vi.stubGlobal('document', {
            ...document,
            addEventListener: (event: string, handler: () => void) => {
                if (event === 'visibilitychange') {
                    visibilityHandler = handler;
                }
            },
            visibilityState: 'hidden',
        });
        vi.stubGlobal('navigator', { serviceWorker: { register, addEventListener: vi.fn() } });

        await registerServiceWorker({
            serviceWorkerUrl: '/sw.js',
            scope: '/',
            checkUpdatesOnVisibility: true,
        });

        Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
        visibilityHandler?.();
        expect(update).toHaveBeenCalled();
    });

    it('registerServiceWorker reloads when controller changes', async () => {
        const reload = vi.fn();
        Object.defineProperty(window, 'location', {
            value: { reload },
            configurable: true,
        });
        vi.stubGlobal('navigator', {
            serviceWorker: {
                register: vi.fn(async () => ({})),
                addEventListener: (_event: string, listener: () => void) => listener(),
            },
        });
        vi.stubGlobal('document', { ...document, addEventListener: vi.fn() });

        await registerServiceWorker({
            serviceWorkerUrl: '/sw.js',
            scope: '/',
            reloadOnUpdate: true,
        });

        expect(reload).toHaveBeenCalled();
    });

    it('isPwaInstalled returns false without matchMedia support', () => {
        Object.defineProperty(window, 'matchMedia', { configurable: true, value: undefined });

        expect(isPwaInstalled()).toBe(false);
    });

    it('isPwaInstalled detects iOS standalone mode', () => {
        vi.spyOn(window, 'matchMedia').mockReturnValue({
            matches: false,
            media: '',
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        } as MediaQueryList);
        Object.defineProperty(navigator, 'standalone', { configurable: true, value: true });

        expect(isPwaInstalled()).toBe(true);
    });

    it('isPwaInstalled detects standalone display mode', () => {
        vi.spyOn(window, 'matchMedia').mockImplementation((query: string) => ({
            matches: query === '(display-mode: standalone)',
            media: query,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        } as MediaQueryList));

        expect(isPwaInstalled()).toBe(true);
    });

    it('isPwaInstalledAsync uses getInstalledRelatedApps', async () => {
        vi.spyOn(window, 'matchMedia').mockReturnValue({
            matches: false,
            media: '',
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        } as MediaQueryList);
        vi.stubGlobal('navigator', {
            getInstalledRelatedApps: vi.fn(async () => [{}]),
        });

        await expect(isPwaInstalledAsync()).resolves.toBe(true);
    });

    it('setupInstallLinks exits when container is missing', () => {
        expect(() => setupInstallLinks(document)).not.toThrow();
    });

    it('setupInstallLinks toggles install and uninstall links', async () => {
        document.body.innerHTML = installLinksHtml();
        setupInstallLinks(document);

        const installLink = document.querySelector('[data-pwa-install-action="install"]') as HTMLAnchorElement;
        const uninstallLink = document.querySelector('[data-pwa-install-action="uninstall"]') as HTMLAnchorElement;

        const prompt = vi.fn(async () => undefined);
        const event = Object.assign(new Event('beforeinstallprompt'), {
            preventDefault: vi.fn(),
            prompt,
        });
        window.dispatchEvent(event);
        await Promise.resolve();

        expect(installLink.hidden).toBe(false);
        expect(uninstallLink.hidden).toBe(true);

        installLink.click();
        await Promise.resolve();
        expect(prompt).toHaveBeenCalled();

        window.dispatchEvent(new Event('appinstalled'));
        expect(installLink.hidden).toBe(true);
        expect(uninstallLink.hidden).toBe(false);

        const alert = vi.spyOn(window, 'alert').mockImplementation(() => undefined);
        uninstallLink.click();
        expect(alert).toHaveBeenCalledWith('Remove the app');
    });

    it('setupInstallLinks skips desktop when visibility is mobile', () => {
        Object.defineProperty(navigator, 'userAgent', {
            value: 'Mozilla/5.0 (Windows NT 10.0)',
            configurable: true,
        });
        document.body.innerHTML = installLinksHtml().replace(
            'id="nowo-pwa-install-links"',
            'id="nowo-pwa-install-links" data-visibility="mobile"',
        );
        setupInstallLinks(document);
        window.dispatchEvent(new Event('beforeinstallprompt'));
        const installLink = document.querySelector('[data-pwa-install-action="install"]') as HTMLAnchorElement;
        expect(installLink.hidden).toBe(true);
    });

    it('setupInstallLinks ignores install click without deferred prompt', () => {
        document.body.innerHTML = installLinksHtml();
        setupInstallLinks(document);

        const installLink = document.querySelector('[data-pwa-install-action="install"]') as HTMLAnchorElement;
        installLink.hidden = false;
        installLink.click();

        expect(installLink.hidden).toBe(false);
    });

    it('isPwaInstalledAsync returns true when display mode is standalone', async () => {
        vi.spyOn(window, 'matchMedia').mockImplementation((query: string) => ({
            matches: query === '(display-mode: standalone)',
            media: query,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        } as MediaQueryList));

        await expect(isPwaInstalledAsync()).resolves.toBe(true);
    });

    it('isPwaInstalledAsync returns false without related apps API', async () => {
        vi.spyOn(window, 'matchMedia').mockReturnValue({
            matches: false,
            media: '',
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        } as MediaQueryList);
        vi.stubGlobal('navigator', {});

        await expect(isPwaInstalledAsync()).resolves.toBe(false);
    });

    it('setupInstallLinks exits when links are not anchor elements', () => {
        document.body.innerHTML = `
            <div id="nowo-pwa-install-links">
                <button data-pwa-install-action="install">Install</button>
                <button data-pwa-install-action="uninstall">Uninstall</button>
            </div>
        `;
        expect(() => setupInstallLinks(document)).not.toThrow();
    });

    it('setupInstallLinks shows uninstall link when app is already installed', async () => {
        vi.spyOn(window, 'matchMedia').mockImplementation((query: string) => ({
            matches: query === '(display-mode: standalone)',
            media: query,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        } as MediaQueryList));

        document.body.innerHTML = installLinksHtml();
        setupInstallLinks(document);
        await Promise.resolve();

        const installLink = document.querySelector('[data-pwa-install-action="install"]') as HTMLAnchorElement;
        const uninstallLink = document.querySelector('[data-pwa-install-action="uninstall"]') as HTMLAnchorElement;
        expect(installLink.hidden).toBe(true);
        expect(uninstallLink.hidden).toBe(false);
    });

    it('setupInstallLinks uses default uninstall help text', () => {
        const alert = vi.spyOn(window, 'alert').mockImplementation(() => undefined);
        document.body.innerHTML = `
            <div id="nowo-pwa-install-links">
                <a href="#" hidden data-pwa-install-action="install">Install app</a>
                <a href="#" data-pwa-install-action="uninstall">Uninstall app</a>
            </div>
        `;
        setupInstallLinks(document);

        const uninstallLink = document.querySelector('[data-pwa-install-action="uninstall"]') as HTMLAnchorElement;
        uninstallLink.click();

        expect(alert).toHaveBeenCalledWith('To uninstall, remove the app from your device or browser.');
    });

    it('setupInstallLinks keeps both links hidden until installable', async () => {
        vi.spyOn(window, 'matchMedia').mockReturnValue({
            matches: false,
            media: '',
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        } as MediaQueryList);
        vi.stubGlobal('navigator', {});

        document.body.innerHTML = installLinksHtml();
        setupInstallLinks(document);
        await Promise.resolve();

        const installLink = document.querySelector('[data-pwa-install-action="install"]') as HTMLAnchorElement;
        const uninstallLink = document.querySelector('[data-pwa-install-action="uninstall"]') as HTMLAnchorElement;
        expect(installLink.hidden).toBe(true);
        expect(uninstallLink.hidden).toBe(true);
    });

    it('setupInstallLinks skips display-mode listeners without matchMedia', () => {
        const originalMatchMedia = window.matchMedia;
        Object.defineProperty(window, 'matchMedia', { configurable: true, value: undefined });

        document.body.innerHTML = installLinksHtml();
        expect(() => setupInstallLinks(document)).not.toThrow();

        Object.defineProperty(window, 'matchMedia', { configurable: true, value: originalMatchMedia });
    });

    it('setupInstallLinks refreshes on display-mode and visibility change', async () => {
        const displayModeListeners: Record<string, () => void> = {};
        let visibilityHandler: (() => void) | undefined;
        const addEventListenerSpy = vi.spyOn(document, 'addEventListener').mockImplementation((event, handler) => {
            if (event === 'visibilitychange' && typeof handler === 'function') {
                visibilityHandler = handler as () => void;
            }
        });
        vi.spyOn(window, 'matchMedia').mockImplementation((query: string) => ({
            matches: false,
            media: query,
            addEventListener: (_event: string, handler: () => void) => {
                displayModeListeners[query] = handler;
            },
            removeEventListener: vi.fn(),
        } as MediaQueryList));
        vi.stubGlobal('navigator', {
            getInstalledRelatedApps: vi.fn(async () => []),
        });

        document.body.innerHTML = installLinksHtml();
        setupInstallLinks(document);

        Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
        visibilityHandler?.();
        displayModeListeners['(display-mode: standalone)']?.();
        await Promise.resolve();

        addEventListenerSpy.mockRestore();
    });

    it('isPwaInstalledAsync returns false when related apps lookup fails', async () => {
        vi.stubGlobal('navigator', {
            getInstalledRelatedApps: vi.fn(async () => {
                throw new Error('unsupported');
            }),
        });

        await expect(isPwaInstalledAsync()).resolves.toBe(false);
    });
});
