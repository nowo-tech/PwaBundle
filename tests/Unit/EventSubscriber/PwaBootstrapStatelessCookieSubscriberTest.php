<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\EventSubscriber;

use Nowo\PwaBundle\EventSubscriber\PwaBootstrapStatelessCookieSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class PwaBootstrapStatelessCookieSubscriberTest extends TestCase
{
    public function testStripsSetCookieOnManifest(): void
    {
        $subscriber = new PwaBootstrapStatelessCookieSubscriber(true, [
            '/manifest.webmanifest',
            '/sw.js',
        ]);
        $response = new Response('{}');
        $response->headers->setCookie(new Cookie('PHPHFASESSID', 'guest'));
        $event = $this->event('/manifest.webmanifest', $response);

        $subscriber->onKernelResponse($event);

        self::assertNull($response->headers->get('Set-Cookie'));
        self::assertSame([], $response->headers->getCookies());
    }

    public function testLeavesOtherPathsAlone(): void
    {
        $subscriber = new PwaBootstrapStatelessCookieSubscriber(true, [
            '/manifest.webmanifest',
            '/sw.js',
        ]);
        $response = new Response('ok');
        $response->headers->setCookie(new Cookie('PHPHFASESSID', 'user'));
        $event = $this->event('/admin', $response);

        $subscriber->onKernelResponse($event);

        self::assertNotEmpty($response->headers->getCookies());
    }

    public function testDisabledDoesNothing(): void
    {
        $subscriber = new PwaBootstrapStatelessCookieSubscriber(false, ['/sw.js']);
        $response = new Response('js');
        $response->headers->setCookie(new Cookie('PHPHFASESSID', 'guest'));
        $event = $this->event('/sw.js', $response);

        $subscriber->onKernelResponse($event);

        self::assertNotEmpty($response->headers->getCookies());
    }

    private function event(string $path, Response $response): ResponseEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create($path);

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
