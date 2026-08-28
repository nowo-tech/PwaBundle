<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keep PWA bootstrap endpoints from minting / rotating session cookies.
 *
 * Browsers often fetch the manifest (and sometimes the service worker) without
 * sending the authenticated session cookie. If Symfony starts a guest session and
 * returns Set-Cookie, the browser overwrites the logged-in session cookie.
 */
final class PwaBootstrapStatelessCookieSubscriber implements EventSubscriberInterface
{
    /**
     * @param list<string> $bootstrapPaths Absolute path prefixes/paths to strip (e.g. /manifest.webmanifest, /sw.js)
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly array $bootstrapPaths,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After SessionListener / CSRF / SecurityDataCollector / WebProfiler toolbar inject.
        return [KernelEvents::RESPONSE => ['onKernelResponse', -3072]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (!\in_array($path, $this->bootstrapPaths, true)) {
            return;
        }

        $headers = $event->getResponse()->headers;

        foreach ($headers->getCookies() as $cookie) {
            $headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }
        $headers->remove('Set-Cookie');
    }
}
