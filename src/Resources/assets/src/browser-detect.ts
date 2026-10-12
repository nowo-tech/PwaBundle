/**
 * Best-effort browser family detection for install (PWA) and Web Push help hints.
 *
 * Order matters: Chromium forks (Brave, Edge, Opera, Samsung) also send "Chrome",
 * and every iOS browser is WebKit (Web Push only works from a Home Screen web app there).
 */

export const BROWSER_IDS = ["brave", "edge", "opera", "samsung", "firefox", "ios", "chrome", "safari", "other"] as const;
export type BrowserId = (typeof BROWSER_IDS)[number];

type BraveNavigator = Navigator & { brave?: { isBrave?: unknown } };

export function detectBrowser(nav: Navigator = navigator): BrowserId {
  const brave = (nav as BraveNavigator).brave;
  if (brave && typeof brave.isBrave === "function") {
    return "brave";
  }
  const ua = nav.userAgent;
  if (/SamsungBrowser/i.test(ua)) {
    return "samsung";
  }
  if (/OPR\/|Opera|OPT\//i.test(ua)) {
    return "opera";
  }
  if (/Edg(e|A|iOS)?\//i.test(ua)) {
    return "edge";
  }
  if (/iPhone|iPad|iPod/i.test(ua) || (/Macintosh/i.test(ua) && nav.maxTouchPoints > 1)) {
    return "ios";
  }
  if (/Firefox\/|FxiOS/i.test(ua)) {
    return "firefox";
  }
  if (/Chrome\/|Chromium\/|CriOS/i.test(ua)) {
    return "chrome";
  }
  if (/Safari\//i.test(ua)) {
    return "safari";
  }

  return "other";
}
