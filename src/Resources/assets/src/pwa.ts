/**
 * Registers the configurable service worker and optional install prompt UI.
 */
import { registerServiceWorker, setupInstallLinks, setupInstallPrompt } from './pwa-client';

const script = document.querySelector('script[data-pwa-sw-url]') as HTMLScriptElement | null;

if (script) {
    registerServiceWorker({
        serviceWorkerUrl: script.dataset.pwaSwUrl ?? '',
        scope: script.dataset.pwaScope ?? '/',
        checkUpdatesOnVisibility: script.dataset.pwaCheckUpdates !== '0',
        reloadOnUpdate: script.dataset.pwaReloadOnUpdate === '1',
    });
}

setupInstallPrompt(document);
setupInstallLinks(document);
