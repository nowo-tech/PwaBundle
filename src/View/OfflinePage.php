<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\View;

use Nowo\PwaBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\View\AuthPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\Link;

/**
 * View model of the offline fallback page (REQ-UI-003): a UiKit {@see AuthPage} rendered by the `page/auth`
 * scaffold on the self-contained status layout (inline CSS, no stylesheet / script request, no session), so the
 * service worker can cache it and serve it without network.
 */
final class OfflinePage
{
    public const BUNDLE = 'NowoPwaBundle';

    /**
     * @param string $appName manifest name, shown as the brand line
     * @param string $retryUrl target of the "Try again" link (manifest start_url)
     * @param string|null $layout layout of the page (default: the UiKit status layout); null = nowo_ui_layout.guest
     */
    public static function create(string $appName, string $retryUrl, ?string $layout = Configuration::OFFLINE_LAYOUT): AuthPage
    {
        return new AuthPage(
            new Label('offline.heading', self::BUNDLE),
            intro: new Label('offline.body', self::BUNDLE),
            brand: $appName !== '' ? $appName : null,
            links: [new Link(new Label('offline.retry', self::BUNDLE), $retryUrl !== '' ? $retryUrl : '/', ['data-testid' => 'pwa-offline-retry'])],
            bundle: self::BUNDLE,
            layout: $layout,
            attributes: ['data-testid' => 'pwa-offline'],
            scripts: false,
        );
    }
}
