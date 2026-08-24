export interface PwaClientOptions {
    serviceWorkerUrl: string;
    scope: string;
    checkUpdatesOnVisibility?: boolean;
    reloadOnUpdate?: boolean;
}

/**
 * Registers the service worker when the browser supports it.
 *
 * @param options - Service worker URL, scope, and optional update behaviour.
 * @returns The registration, or `null` when unsupported or registration fails.
 */
export async function registerServiceWorker(options: PwaClientOptions): Promise<ServiceWorkerRegistration | null> {
    if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) {
        return null;
    }

    if (!options.serviceWorkerUrl) {
        return null;
    }

    try {
        const registration = await navigator.serviceWorker.register(options.serviceWorkerUrl, {
            scope: options.scope,
        });

        if (options.checkUpdatesOnVisibility ?? true) {
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    registration.update().catch(() => undefined);
                }
            });
        }

        if (options.reloadOnUpdate ?? false) {
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                window.location.reload();
            });
        }

        return registration;
    } catch {
        return null;
    }
}

/** Wires install prompt UI when present in the DOM. */
export function setupInstallPrompt(doc: Document): void {
    const banner = doc.getElementById('nowo-pwa-install');
    if (!banner) {
        return;
    }

    const visibility = banner.dataset.visibility ?? 'all';
    if (!matchesVisibility(visibility)) {
        return;
    }

    const dismissKey = banner.dataset.dismissKey ?? 'nowo_pwa_install_dismissed';
    const dismissDays = Number(banner.dataset.dismissDays ?? '7');
    const neverDismissKey = banner.dataset.neverDismissKey ?? 'nowo_pwa_install_never';
    const delayMs = Number(banner.dataset.delayMs ?? '0');
    const display = banner.dataset.display ?? 'banner';

    if (shouldHideInstallPrompt(dismissKey, dismissDays, neverDismissKey)) {
        return;
    }

    let deferredPrompt: BeforeInstallPromptEvent | null = null;

    const hidePrompt = (): void => {
        banner.hidden = true;
        if (display === 'modal') {
            doc.body.classList.remove('nowo-pwa-install-modal-open');
        }
    };

    const showPrompt = (): void => {
        const reveal = (): void => {
            banner.hidden = false;
            if (display === 'modal') {
                doc.body.classList.add('nowo-pwa-install-modal-open');
            }
        };

        if (delayMs > 0) {
            window.setTimeout(reveal, delayMs);

            return;
        }

        reveal();
    };

    window.addEventListener('beforeinstallprompt', (event: Event) => {
        event.preventDefault();
        deferredPrompt = event as BeforeInstallPromptEvent;
        showPrompt();
    });

    banner.querySelector('[data-pwa-install-action="install"]')?.addEventListener('click', async () => {
        if (!deferredPrompt) {
            return;
        }
        await deferredPrompt.prompt();
        deferredPrompt = null;
        hidePrompt();
    });

    banner.querySelector('[data-pwa-install-action="dismiss-remind"]')?.addEventListener('click', () => {
        storeDismiss(dismissKey, dismissDays);
        hidePrompt();
    });

    banner.querySelector('[data-pwa-install-action="dismiss-never"]')?.addEventListener('click', () => {
        storeNeverDismiss(neverDismissKey);
        hidePrompt();
    });

    // Backward compatibility with legacy dismiss action name.
    banner.querySelector('[data-pwa-install-action="dismiss"]')?.addEventListener('click', () => {
        storeDismiss(dismissKey, dismissDays);
        hidePrompt();
    });
}

/** Returns true when the app runs as an installed PWA (standalone / iOS home screen). */
export function isPwaInstalled(): boolean {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return false;
    }

    const navigatorWithStandalone = navigator as Navigator & { standalone?: boolean };
    if (navigatorWithStandalone.standalone === true) {
        return true;
    }

    return ['standalone', 'fullscreen', 'minimal-ui'].some(
        (mode) => window.matchMedia(`(display-mode: ${mode})`).matches,
    );
}

/** Detects installation from display mode or InstalledRelatedApps when available. */
export async function isPwaInstalledAsync(): Promise<boolean> {
    if (isPwaInstalled()) {
        return true;
    }

    const navigatorWithApps = navigator as Navigator & {
        getInstalledRelatedApps?: () => Promise<unknown[]>;
    };

    if (typeof navigatorWithApps.getInstalledRelatedApps !== 'function') {
        return false;
    }

    try {
        const apps = await navigatorWithApps.getInstalledRelatedApps();

        return apps.length > 0;
    } catch {
        return false;
    }
}

/** Wires install / uninstall links; only one link is visible at a time. */
export function setupInstallLinks(doc: Document): void {
    const container = doc.getElementById('nowo-pwa-install-links');
    if (!container) {
        return;
    }

    const visibility = container.dataset.visibility ?? 'all';
    if (!matchesVisibility(visibility)) {
        return;
    }

    const installLink = container.querySelector('[data-pwa-install-action="install"]');
    const uninstallLink = container.querySelector('[data-pwa-install-action="uninstall"]');
    if (!(installLink instanceof HTMLAnchorElement) || !(uninstallLink instanceof HTMLAnchorElement)) {
        return;
    }

    const uninstallHelp = container.dataset.uninstallHelp ?? 'To uninstall, remove the app from your device or browser.';

    let deferredPrompt: BeforeInstallPromptEvent | null = null;

    const showInstalled = (): void => {
        installLink.hidden = true;
        uninstallLink.hidden = false;
    };

    const showInstallable = (): void => {
        installLink.hidden = false;
        uninstallLink.hidden = true;
    };

    const hideBoth = (): void => {
        installLink.hidden = true;
        uninstallLink.hidden = true;
    };

    const updateState = async (): Promise<void> => {
        if (await isPwaInstalledAsync()) {
            showInstalled();

            return;
        }

        if (deferredPrompt !== null) {
            showInstallable();

            return;
        }

        hideBoth();
    };

    window.addEventListener('beforeinstallprompt', (event: Event) => {
        event.preventDefault();
        deferredPrompt = event as BeforeInstallPromptEvent;
        void updateState();
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        showInstalled();
    });

    installLink.addEventListener('click', async (event) => {
        event.preventDefault();
        if (deferredPrompt === null) {
            return;
        }

        await deferredPrompt.prompt();
        deferredPrompt = null;
        await updateState();
    });

    uninstallLink.addEventListener('click', (event) => {
        event.preventDefault();
        window.alert(uninstallHelp);
    });

    void updateState();

    doc.addEventListener('visibilitychange', () => {
        if (doc.visibilityState === 'visible') {
            void updateState();
        }
    });

    for (const mode of ['standalone', 'fullscreen', 'minimal-ui']) {
        if (typeof window.matchMedia !== 'function') {
            break;
        }

        window.matchMedia(`(display-mode: ${mode})`).addEventListener('change', () => {
            void updateState();
        });
    }
}

export function shouldHideInstallPrompt(
    dismissKey: string,
    dismissDays: number,
    neverDismissKey = 'nowo_pwa_install_never',
    now = Date.now(),
): boolean {
    if (localStorage.getItem(neverDismissKey) === '1') {
        return true;
    }

    const raw = localStorage.getItem(dismissKey);
    if (!raw) {
        return false;
    }

    const dismissedAt = Number(raw);
    if (Number.isNaN(dismissedAt)) {
        return false;
    }

    const ttlMs = dismissDays * 24 * 60 * 60 * 1000;

    return now - dismissedAt < ttlMs;
}

export function storeDismiss(dismissKey: string, dismissDays: number, now = Date.now()): void {
    if (dismissDays <= 0) {
        localStorage.removeItem(dismissKey);

        return;
    }

    localStorage.setItem(dismissKey, String(now));
}

export function storeNeverDismiss(neverDismissKey: string): void {
    localStorage.setItem(neverDismissKey, '1');
}

function matchesVisibility(visibility: string): boolean {
    if (visibility === 'mobile') {
        return isMobileUserAgent();
    }

    if (visibility === 'desktop') {
        return !isMobileUserAgent();
    }

    return true;
}

function isMobileUserAgent(): boolean {
    return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
}

interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
}
