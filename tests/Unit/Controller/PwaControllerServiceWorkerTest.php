<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Controller;

use Nowo\PwaBundle\Controller\PwaController;
use Nowo\PwaBundle\Service\ManifestBuilder;
use Nowo\PwaBundle\Service\ServiceWorkerScriptBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PwaControllerServiceWorkerTest extends TestCase
{
    private const ROUTES = [
        'manifest'       => ['path' => '/manifest.webmanifest', 'name' => 'nowo_pwa_manifest'],
        'service_worker' => ['path' => '/sw.js', 'name' => 'nowo_pwa_service_worker'],
        'offline'        => ['path' => '/offline', 'name' => 'nowo_pwa_offline'],
    ];

    public function testOfflineUrlIsGeneratedFromRouteSoRoutePrefixIsHonoured(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('nowo_pwa_offline', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.test/pwa/offline');

        $script = $this->script([], $urlGenerator);

        self::assertStringContainsString('"offlineUrl":"https://example.test/pwa/offline"', $script);
        self::assertStringContainsString('"precacheUrls":["/","https://example.test/pwa/offline"]', $script);
        self::assertStringNotContainsString('"https://example.test/offline"', $script);
    }

    public function testConfiguredOfflineUrlWins(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $script = $this->script(['offline_url' => '/es/sin-conexion'], $urlGenerator);

        self::assertStringContainsString('"offlineUrl":"/es/sin-conexion"', $script);
        self::assertStringContainsString('"precacheUrls":["/","/es/sin-conexion"]', $script);
    }

    public function testFallsBackToRoutePathWithoutUrlGenerator(): void
    {
        $script = $this->script([], null);

        self::assertStringContainsString('"offlineUrl":"https://example.test/offline"', $script);
    }

    public function testServiceWorkerResponseHeaders(): void
    {
        $response = $this->controller([], null)->serviceWorker(Request::create('https://example.test/sw.js'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/javascript; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('/', $response->headers->get('Service-Worker-Allowed'));
    }

    /**
     * @param array<string, mixed> $serviceWorkerConfig
     */
    private function script(array $serviceWorkerConfig, ?UrlGeneratorInterface $urlGenerator): string
    {
        return (string) $this->controller($serviceWorkerConfig, $urlGenerator)
            ->serviceWorker(Request::create('https://example.test/pwa/sw.js'))
            ->getContent();
    }

    /**
     * @param array<string, mixed> $serviceWorkerConfig
     */
    private function controller(array $serviceWorkerConfig, ?UrlGeneratorInterface $urlGenerator): PwaController
    {
        return new PwaController(
            true,
            new ManifestBuilder(),
            new ServiceWorkerScriptBuilder(),
            ['name' => 'App'],
            $serviceWorkerConfig + ['precache_urls' => ['/']],
            ['service_worker_cache_max_age' => 0],
            ['head' => '', 'install_prompt' => '', 'offline' => ''],
            self::ROUTES,
            [],
            $urlGenerator,
        );
    }
}
