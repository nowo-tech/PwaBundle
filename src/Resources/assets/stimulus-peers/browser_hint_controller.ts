import { Controller } from "@hotwired/stimulus";
import { detectBrowser } from "../src/browser-detect";

export { BROWSER_IDS, detectBrowser, type BrowserId } from "../src/browser-detect";

/**
 * `browser-hint` — flags `<html data-browser="…">` (so CSS can swap install / push hints) and
 * reveals only the `hint` targets whose `data-browser-hint-id` matches the detected browser.
 *
 * Markup: `@NowoPwaBundle/pwa/browser_hint.html.twig` / `browser_guide.html.twig`.
 */
export default class extends Controller<HTMLElement> {
  static targets = ["hint"];

  declare readonly hintTargets: HTMLElement[];

  connect(): void {
    const browser = detectBrowser();
    document.documentElement.dataset.browser = browser;
    for (const hint of this.hintTargets) {
      hint.hidden = hint.dataset.browserHintId !== browser;
    }
  }
}
