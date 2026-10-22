<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Controller;

use Nowo\PwaBundle\Controller\PwaController;
use Nowo\PwaBundle\Service\ManifestBuilder;
use Nowo\PwaBundle\Service\ServiceWorkerScriptBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function json_decode;

/**
 * Disabled-feature guards and cacheable manifest responses of PwaController (controllers are part of the
 * coverage scope since 1.10.2, REQ-TEST-003).
 */
final class PwaControllerGuardsTest extends TestCase
{
    public function testManifestIsNotFoundWhenPwaIsDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(false)->manifest(Request::create('/manifest.webmanifest'));
    }

    public function testServiceWorkerIsNotFoundWhenItIsDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(true, ['enabled' => false])->serviceWorker(Request::create('/sw.js'));
    }

    public function testManifestUsesAnAbsoluteStartUrlAndPublicCacheHeaders(): void
    {
        $controller = $this->controller(true, [], ['manifest_cache_max_age' => 600, 'manifest_public_cache' => true], ['name' => 'App', 'short_name' => 'App', 'start_url' => '/home']);
        $response   = $controller->manifest(Request::create('https://example.com/manifest.webmanifest'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getContent(), true);

        self::assertSame('https://example.com/home', $payload['start_url']);
        self::assertSame('max-age=600, public', $response->headers->get('Cache-Control'));
    }

    /**
     * @param array<string, mixed> $serviceWorker
     * @param array<string, mixed> $http
     * @param array<string, mixed> $manifest
     */
    private function controller(bool $enabled, array $serviceWorker = [], array $http = [], array $manifest = ['name' => 'App']): PwaController
    {
        return new PwaController(
            $enabled,
            new ManifestBuilder(),
            new ServiceWorkerScriptBuilder(),
            $manifest,
            $serviceWorker,
            $http,
            ['head' => '', 'install_prompt' => '', 'offline' => ''],
            [],
        );
    }
}
