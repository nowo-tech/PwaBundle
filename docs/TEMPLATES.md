# Templates

PwaBundle follows the UiKit 2.x **page contract** (REQ-UI-003, `nowo-tech/ui-kit-bundle` ^2.2). It ships one page
(the offline fallback, a unique page on the UiKit status layout) and a few **widgets** that live inside the host's
own pages (install prompt, install links, push diagnostics, browser hint / guide, head tags). The widgets read the UiKit
**theme slots** (`nowo_ui_class()` / `nowo_ui_attrs()` with the bundle name `NowoPwaBundle`) and paint themselves from
tokens that fall back to the UiKit `--nowo-ui-*` tokens. References: UiKit `docs/PAGE_CONTRACT.md`,
`docs/THEME_SLOTS.md`.

## Table of contents

- [Pages](#pages)
- [Widgets](#widgets)
- [Unique pages](#unique-pages)
- [Per-page overrides](#per-page-overrides)
  - [Example: replace the brand mark of the install prompt](#example-replace-the-brand-mark-of-the-install-prompt)
- [Layout of the offline page](#layout-of-the-offline-page)
- [Tokens](#tokens)
- [Allowed exceptions](#allowed-exceptions)

## Pages

The bundle has no page that fits a scaffold: its only page is listed under [Unique pages](#unique-pages).

Context: `page` (the `AuthPage`) and the 1.x variables `manifest_name`, `manifest`, `retry_url` (kept for host
templates set through `web_ui.templates.offline`).

The status layout is a complete, self-contained document: inline critical CSS (with the request `csp_nonce` when
there is one) that reads the `--nowo-ui-*` tokens, the theme tokens (`nowo_ui_kit.theme.tokens`), no stylesheet or
script request and no session flashes — the service worker can serve it from its cache while the network is down.

## Widgets

Widgets are not pages (no scaffold, no layout); they use theme slots so a host themes them once in
`nowo_ui_kit.theme` (or per bundle in `nowo_ui_kit.theme.bundles.NowoPwaBundle`).

| Widget | Template | Theme slots | Blocks |
| --- | --- | --- | --- |
| Install prompt (`nowo_pwa_install_prompt()`) | `pwa/install_prompt.html.twig` | panel `card` (+ attributes), title `card_title`, message `card_intro`, eyebrow `muted`, actions `toolbar` (+ attributes), install `btn_primary`, "Not now" `btn_secondary`, "Don't ask again" `btn_link` | `pwa_install_backdrop`, `pwa_install_panel`, `pwa_install_mark`, `pwa_install_text`, `pwa_install_eyebrow`, `pwa_install_title`, `pwa_install_message`, `pwa_install_actions`, `pwa_install_button_install`, `pwa_install_button_remind`, `pwa_install_button_never` |
| Install / uninstall links (`nowo_pwa_install_links()`) | `pwa/install_links.html.twig` | `btn_primary` + `btn_sm` (install), `btn_secondary` + `btn_sm` (uninstall) | `pwa_install_link`, `pwa_uninstall_link` |
| Push diagnostics | `pwa/push_diagnostics.html.twig` | actions `toolbar`, check `btn_primary`, test `btn_secondary` | `push_diagnostics_header`, `push_diagnostics_actions` |
| Browser hint / guide | `pwa/browser_hint.html.twig`, `pwa/browser_guide.html.twig` | — (BEM classes only) | — |
| Head tags (`nowo_pwa_head()`) | `pwa/head.html.twig` | — | — |

Every widget root carries `data-nowo-ui-bundle="NowoPwaBundle"` (install prompt, install links), so per-bundle tokens
(`theme.bundles.NowoPwaBundle.tokens`) apply to it. The BEM classes (`nowo-pwa-install__*`, configurable root
`install_prompt.css_class`) stay next to the slot classes; the JavaScript hooks are the `data-pwa-install-action`
attributes and the `nowo-pwa-install` / `nowo-pwa-install-links` ids.

## Unique pages

- `pwa/offline.html.twig` — offline fallback (`nowo_pwa_offline`, cached by the service worker). Extends
  `page.layout ?? nowo_ui_layout.guest`; `page.layout` defaults to `@NowoUiKit/layout/status.html.twig`
  (`web_ui.layout_template`). View model: `AuthPage` built by `Nowo\PwaBundle\View\OfflinePage::create()` (title
  `offline.heading`, intro `offline.body`, brand = manifest `name`, links = "Try again" → `manifest.start_url`,
  `scripts: false`, attributes `data-testid="pwa-offline"`). Fills `nowo_ui_document_title` ("Offline — <name>"),
  `nowo_ui_head` (block `pwa_offline_head`: `theme-color` meta) and `nowo_ui_content`: the `page/auth` panel regions
  and slots (`auth_panel`, `card_body`, `page_title`, `page_intro`, `auth_footer`, `auth_links`) in the blocks
  `pwa_offline_brand`, `pwa_offline_content` and `pwa_offline_actions`. Why not the `page/auth` scaffold: it calls
  `form_start()`, which needs `symfony/form` even without a form, and PwaBundle does not depend on it.

## Per-page overrides

`nowo_pwa.web_ui.templates.<name>` (`head`, `install_prompt`, `install_links`, `offline`; 1.x `nowo_pwa.templates.*`
still works, deprecated): a host template that `{% extends '@!NowoPwaBundle/pwa/….html.twig' %}` and redefines one
block. Do not copy the whole file: upgrades then only need the "Template contract" notes of
[UPGRADING.md](UPGRADING.md).

### Example: replace the brand mark of the install prompt

```yaml
# config/packages/nowo_pwa.yaml
nowo_pwa:
    web_ui:
        templates:
            install_prompt: 'pwa/install_prompt.html.twig'
```

```twig
{# templates/pwa/install_prompt.html.twig #}
{% extends '@!NowoPwaBundle/pwa/install_prompt.html.twig' %}

{% block pwa_install_mark %}
    <div class="{{ bem }}__brand">
        <span class="{{ bem }}__mark" aria-hidden="true">{% include '_brand_icon.html.twig' %}</span>
    </div>
{% endblock %}
```

The template variables of the parent (`bem`, `display`, `install_title`, `install_eyebrow`, `install_prompt`,
`manifest`, …) are available in the block. A file under `templates/bundles/NowoPwaBundle/pwa/` with the same
`{% extends '@!NowoPwaBundle/…' %}` works too, without configuration.

## Layout of the offline page

`nowo_pwa.web_ui.layout_template` — default `null` = `@NowoUiKit/layout/status.html.twig`. Set it to
`@NowoUiKit/layout/guest.html.twig` (or your guest layout) only if that layout and its assets are cached by the
service worker: the offline page is shown precisely when the network is not available.

## Tokens

`pwa.css` paints the widgets from `--nowo-pwa-install-*` / `--nowo-pwa-install-links-*` tokens whose defaults read the
UiKit tokens first (`--nowo-ui-surface`, `--nowo-ui-text`, `--nowo-ui-muted`, `--nowo-ui-border`, `--nowo-ui-radius`,
`--nowo-ui-primary`, `--nowo-ui-primary-contrast`, `--nowo-ui-surface-muted`) and fall back to the 1.x colours when
the UiKit tokens are not defined. The panel paint uses two-class selectors, so it wins over a framework `card` class
whatever the stylesheet order; host classes on the slots still apply (spacing, typography, extra hooks).

## Allowed exceptions

None: the contract test (`tests/Integration/PageContractTest.php`) runs with the default allow-list.
