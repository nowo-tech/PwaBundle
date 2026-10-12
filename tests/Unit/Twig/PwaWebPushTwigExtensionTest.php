<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Twig;

use Nowo\PwaBundle\Twig\PwaWebPushTwigExtension;
use Nowo\PwaBundle\WebPush\MinishlinkWebPushSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class PwaWebPushTwigExtensionTest extends TestCase
{
    public function testDisabledByDefault(): void
    {
        $extension = new PwaWebPushTwigExtension($this->config(false, false), $this->createMock(UrlGeneratorInterface::class), new MinishlinkWebPushSender('pub', 'priv'));

        self::assertSame('nowo_pwa_web_push', $extension->getFunctions()[0]->getName());
        self::assertSame([
            'enabled'          => false,
            'configured'       => false,
            'vapid_public_key' => null,
            'test_url'         => null,
            'csrf_token_id'    => 'nowo_pwa_push',
            'csrf_token'       => null,
        ], $extension->webPush());
    }

    public function testEnabledWithoutSenderService(): void
    {
        $extension = new PwaWebPushTwigExtension($this->config(true, true), $this->createMock(UrlGeneratorInterface::class));

        self::assertFalse($extension->webPush()['enabled']);
        self::assertNull($extension->webPush()['test_url']);
    }

    public function testExposesKeyTestUrlAndCsrfToken(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->with('nowo_pwa_push_test')->willReturn('/pwa/push/test');
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->with('nowo_pwa_push')->willReturn(new CsrfToken('nowo_pwa_push', 'tok'));

        $data = (new PwaWebPushTwigExtension($this->config(true, true), $urlGenerator, new MinishlinkWebPushSender('pub', 'priv'), $csrf))->webPush();

        self::assertTrue($data['enabled']);
        self::assertTrue($data['configured']);
        self::assertSame('pub', $data['vapid_public_key']);
        self::assertSame('/pwa/push/test', $data['test_url']);
        self::assertSame('tok', $data['csrf_token']);
    }

    public function testNoTestEndpointOrCsrfManager(): void
    {
        $sender = new MinishlinkWebPushSender('pub', '');

        $withoutTest = (new PwaWebPushTwigExtension($this->config(true, false), $this->createMock(UrlGeneratorInterface::class), $sender))->webPush();
        self::assertTrue($withoutTest['enabled']);
        self::assertFalse($withoutTest['configured']);
        self::assertNull($withoutTest['vapid_public_key']);
        self::assertNull($withoutTest['test_url']);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/t');
        $withoutCsrf = (new PwaWebPushTwigExtension($this->config(true, true), $urlGenerator, $sender))->webPush();
        self::assertSame('/t', $withoutCsrf['test_url']);
        self::assertNull($withoutCsrf['csrf_token']);
    }

    /**
     * @return array{enabled: bool, test_enabled: bool, test_route: string, csrf_token_id: string}
     */
    private function config(bool $enabled, bool $testEnabled): array
    {
        return ['enabled' => $enabled, 'test_enabled' => $testEnabled, 'test_route' => 'nowo_pwa_push_test', 'csrf_token_id' => 'nowo_pwa_push'];
    }
}
