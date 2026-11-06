import { describe, expect, it } from 'vitest';
import * as pushChecks from './push-checks';

/** docs/USAGE.md declares these exports a public entry: renaming one is a breaking change. */
describe('push-checks public entry', () => {
    it('keeps the documented exports', () => {
        expect(typeof pushChecks.runPushChecks).toBe('function');
        expect(typeof pushChecks.applicableChecks).toBe('function');
        expect(typeof pushChecks.urlBase64ToUint8Array).toBe('function');
        expect(pushChecks.PUSH_CHECK_ORDER).toContain('browser_subscription');
    });
});
