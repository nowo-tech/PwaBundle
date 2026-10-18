import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 — full demo frame: masthead, the demo page with the install prompt open (themed by the host's UiKit
 * tokens / slots, brand mark from a one-block host override) and the Symfony WebProfiler toolbar; the offline page.
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.use({ viewport: { width: 1280, height: 920 } });

test.describe('feature screenshots', () => {
  test('overview — install prompt open', async ({ page }) => {
    await page.goto('/');
    await page.getByTestId('demo-preview-prompt').click();
    await expect(page.getByTestId('pwa-install-prompt')).toBeVisible();
    await expect(page.locator('.sf-toolbar')).toBeVisible();
    await page.waitForTimeout(300);
    await page.screenshot({ path: resolve(outDir, 'overview.png') });
  });

  test('offline — status page served by the service worker', async ({ page }) => {
    await page.goto('/offline');
    await expect(page.getByTestId('pwa-offline')).toBeVisible();
    await page.waitForTimeout(300);
    await page.screenshot({ path: resolve(outDir, 'offline.png') });
  });
});
