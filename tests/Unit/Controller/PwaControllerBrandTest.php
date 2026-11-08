<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Controller;

use Nowo\Brand\Contract\Brand;
use Nowo\PwaBundle\Brand\BrandManifestOverlayProvider;
use Nowo\PwaBundle\Brand\PwaBrand;
use Nowo\PwaBundle\Controller\PwaController;
use Nowo\PwaBundle\Service\ManifestBuilder;
use Nowo\PwaBundle\Service\ManifestOverlayProviderInterface;
use Nowo\PwaBundle\Service\ServiceWorkerScriptBuilder;
use Nowo\PwaBundle\Tests\Support\BrandFixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;

/**
 * Spec 220 S4: the manifest with and without a brand provider.
 */
final class PwaControllerBrandTest extends TestCase
{
    private const CONFIG = [
        'name'               => 'Original',
        'short_name'         => 'Orig',
        'start_url'          => '/',
        'absolute_start_url' => false,
        'theme_color'        => '#0f172a',
        'background_color'   => '#ffffff',
        'icons'              => [['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any']],
    ];

    public function testWithoutBrandProviderTheResponseIsUnchanged(): void
    {
        $plain   = $this->controller(null, false)->manifest(Request::create('/manifest.webmanifest'));
        $withPwa = $this->controller(new PwaBrand(), true)->manifest(Request::create('/manifest.webmanifest'));
        $failing = $this->controller(new PwaBrand(BrandFixtures::failingProvider()), true)->manifest(Request::create('/manifest.webmanifest'));

        foreach ([$withPwa, $failing] as $response) {
            self::assertSame($plain->getContent(), $response->getContent());
            self::assertSame($plain->getStatusCode(), $response->getStatusCode());
            self::assertSame($plain->headers->get('Cache-Control'), $response->headers->get('Cache-Control'));
            self::assertSame($plain->headers->get('Content-Type'), $response->headers->get('Content-Type'));
            self::assertNull($response->getEtag());
        }
    }

    public function testBrandIsAppliedAndTheEtagFollowsItsVersion(): void
    {
        $brand    = BrandFixtures::appIconBrand();
        $response = $this->controller($this->pwa($brand), true)->manifest(Request::create('/manifest.webmanifest'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getContent(), true);
        self::assertSame('Acme Portal', $payload['name']);
        self::assertSame('#1f6f54', $payload['theme_color']);
        self::assertSame('#f3f6f4', $payload['background_color']);
        self::assertCount(2, $payload['icons']);
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));

        $etag = (string) $response->getEtag();
        self::assertStringStartsWith('"' . $brand->version . '-', $etag);

        $other = $this->controller($this->pwa(BrandFixtures::brand('Other Brand')), true)->manifest(Request::create('/manifest.webmanifest'));
        self::assertNotSame($etag, $other->getEtag());

        $again = $this->controller($this->pwa(BrandFixtures::appIconBrand()), true)->manifest(Request::create('/manifest.webmanifest'));
        self::assertSame($etag, $again->getEtag());
    }

    public function testMatchingIfNoneMatchGetsNotModified(): void
    {
        $controller = $this->controller($this->pwa(BrandFixtures::brand()), true);
        $etag       = (string) $controller->manifest(Request::create('/manifest.webmanifest'))->getEtag();

        $request = Request::create('/manifest.webmanifest', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        $cached  = $controller->manifest($request);
        self::assertSame(Response::HTTP_NOT_MODIFIED, $cached->getStatusCode());
        self::assertSame('', $cached->getContent());

        $stale = Request::create('/manifest.webmanifest', server: ['HTTP_IF_NONE_MATCH' => '"0000000000000000-0000000000000000"']);
        self::assertSame(Response::HTTP_OK, $controller->manifest($stale)->getStatusCode());
    }

    public function testHostOverlaysRunAfterTheBrand(): void
    {
        $host = new class implements ManifestOverlayProviderInterface {
            public function overlay(array $manifest): array
            {
                $manifest['theme_color'] = '#222222';

                return $manifest;
            }
        };
        $pwa = $this->pwa(BrandFixtures::brand());

        $response = $this->controller($pwa, false, [new BrandManifestOverlayProvider($pwa), $host])->manifest(Request::create('/manifest.webmanifest'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getContent(), true);
        self::assertSame('Acme Portal', $payload['name']);
        self::assertSame('#222222', $payload['theme_color']);
    }

    public function testBrandNameIsJsonEncodedSafely(): void
    {
        $response = $this->controller($this->pwa(BrandFixtures::brand('Acme </script> & "Co"')), true)->manifest(Request::create('/manifest.webmanifest'));
        $content  = (string) $response->getContent();

        self::assertStringNotContainsString('</script>', $content);
        self::assertStringContainsString('\u003C\/script\u003E', $content);
        self::assertStringContainsString('\u0026', $content);
        self::assertStringContainsString('\u0022Co\u0022', $content);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($content, true);
        self::assertSame('Acme </script> & "Co"', $payload['name']);
    }

    private function pwa(Brand $brand): PwaBrand
    {
        return new PwaBrand(BrandFixtures::provider($brand));
    }

    /**
     * @param iterable<ManifestOverlayProviderInterface>|null $overlays null: the brand overlay alone (when $withOverlay)
     */
    private function controller(?PwaBrand $pwa, bool $withOverlay, ?iterable $overlays = null): PwaController
    {
        $overlays ??= $withOverlay && $pwa instanceof PwaBrand ? [new BrandManifestOverlayProvider($pwa)] : [];

        return new PwaController(
            true,
            new ManifestBuilder(),
            new ServiceWorkerScriptBuilder(),
            self::CONFIG,
            [],
            ['manifest_cache_max_age' => 3600, 'manifest_public_cache' => true],
            ['head' => '', 'install_prompt' => '', 'offline' => ''],
            [],
            $overlays,
            null,
            null,
            $pwa,
        );
    }
}
