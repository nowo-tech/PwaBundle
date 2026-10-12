import { Application } from '@hotwired/stimulus';
import { afterEach, describe, expect, it } from 'vitest';
import BrowserHintController, { BROWSER_IDS, detectBrowser } from './browser_hint_controller';

const nav = (userAgent: string, extra: Record<string, unknown> = {}): Navigator =>
    ({ userAgent, maxTouchPoints: 0, ...extra }) as unknown as Navigator;

const CHROME = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';
const MAC_SAFARI = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15';

describe('detectBrowser', () => {
    it.each([
        ['brave', nav(CHROME, { brave: { isBrave: () => Promise.resolve(true) } })],
        ['chrome', nav(CHROME, { brave: { isBrave: true } })],
        ['edge', nav(`${CHROME} Edg/141.0.0.0`)],
        ['opera', nav(`${CHROME} OPR/120.0.0.0`)],
        ['samsung', nav('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 SamsungBrowser/27.0 Chrome/125.0 Mobile Safari/537.36')],
        ['firefox', nav('Mozilla/5.0 (X11; Linux x86_64; rv:143.0) Gecko/20100101 Firefox/143.0')],
        ['ios', nav('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 CriOS/141.0 Mobile/15E148 Safari/604.1')],
        ['ios', nav(MAC_SAFARI, { maxTouchPoints: 5 })],
        ['safari', nav(MAC_SAFARI)],
        ['chrome', nav(CHROME)],
        ['other', nav('curl/8.0')],
    ])('detects %s', (expected, navigator) => {
        expect(detectBrowser(navigator)).toBe(expected);
    });

    it('defaults to the global navigator and exposes every id', () => {
        expect(BROWSER_IDS).toContain(detectBrowser());
        expect(BROWSER_IDS).toHaveLength(9);
    });
});

describe('browser-hint controller', () => {
    let application: Application;

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        Reflect.deleteProperty(navigator, 'brave');
        delete document.documentElement.dataset.browser;
    });

    it('flags the html element and reveals only the matching hint', async () => {
        Object.defineProperty(navigator, 'brave', { configurable: true, value: { isBrave: () => Promise.resolve(true) } });
        document.body.innerHTML = `
            <div data-controller="browser-hint">
                <p data-browser-hint-target="hint" data-browser-hint-id="brave" hidden>brave</p>
                <p data-browser-hint-target="hint" data-browser-hint-id="chrome">chrome</p>
            </div>
        `;
        application = Application.start();
        application.register('browser-hint', BrowserHintController);
        await Promise.resolve();

        expect(document.documentElement.dataset.browser).toBe('brave');
        expect(document.querySelector<HTMLElement>('[data-browser-hint-id="brave"]')!.hidden).toBe(false);
        expect(document.querySelector<HTMLElement>('[data-browser-hint-id="chrome"]')!.hidden).toBe(true);
    });
});
