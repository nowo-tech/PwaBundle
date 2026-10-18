import { test, expect } from '@playwright/test';

/** REQ-DEMO-013 — the PWA demo boots, serves manifest / service worker / offline page and shows the install prompt. */
test.describe('PWA demo', () => {
  test('home page links the manifest and themes the install prompt', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Installable Symfony app' })).toBeVisible();
    await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', '/manifest.webmanifest');

    const prompt = page.getByTestId('pwa-install-prompt');
    await expect(prompt).toBeHidden();
    await page.getByTestId('demo-preview-prompt').click();
    await expect(prompt).toBeVisible();
    await expect(prompt.locator('.nowo-pwa-install__panel')).toHaveClass(/nowo-ui-card demo-card/);
    await expect(prompt.getByRole('heading', { name: 'Install PWA Demo' })).toBeVisible();
    await expect(prompt.getByRole('button', { name: 'Install', exact: true })).toHaveClass(/demo-btn--primary/);
    // Host override of one block (pwa_install_mark), the rest from the bundle.
    await expect(prompt.locator('.demo-mark')).toBeVisible();
    await prompt.getByRole('button', { name: 'Not now' }).click();
    await expect(prompt).toBeHidden();
  });

  test('manifest and service worker respond', async ({ request }) => {
    const manifest = await request.get('/manifest.webmanifest');
    expect(manifest.ok()).toBeTruthy();
    expect((await manifest.json()).name).toBeTruthy();
    const sw = await request.get('/sw.js');
    expect(sw.ok()).toBeTruthy();
    expect(await sw.text()).toContain('nowo-pwa');
  });

  test('offline page is self-contained', async ({ page }) => {
    // Requests of the page itself; the dev tooling (WebProfiler toolbar, FrankenPHP hot reload) is not part of it.
    const devTooling = /^\/(_wdt|_profiler|\.well-known\/mercure)|^\/npm\/(idiomorph|frankenphp-hot-reload)/;
    const external: string[] = [];
    page.on('request', (req) => {
      const url = new URL(req.url());
      if (!devTooling.test(url.pathname) && url.pathname !== '/offline') {
        external.push(url.href);
      }
    });
    await page.goto('/offline');
    await expect(page.getByTestId('pwa-offline')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'You are offline' })).toBeVisible();
    await expect(page.getByTestId('pwa-offline-retry')).toHaveAttribute('href', '/');
    expect(external).toEqual([]);
  });
});
