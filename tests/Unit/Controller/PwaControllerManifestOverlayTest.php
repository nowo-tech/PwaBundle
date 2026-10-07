<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Controller;

use Nowo\PwaBundle\Controller\PwaController;
use Nowo\PwaBundle\Service\ManifestBuilder;
use Nowo\PwaBundle\Service\ManifestOverlayProviderInterface;
use Nowo\PwaBundle\Service\ServiceWorkerScriptBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

use function json_decode;

final class PwaControllerManifestOverlayTest extends TestCase
{
    public function testOverlayProvidersAreAppliedInOrder(): void
    {
        $first = new class implements ManifestOverlayProviderInterface {
            public function overlay(array $manifest): array
            {
                $manifest['name']        = 'Brand';
                $manifest['theme_color'] = '#111111';

                return $manifest;
            }
        };
        $second = new class implements ManifestOverlayProviderInterface {
            public function overlay(array $manifest): array
            {
                $manifest['theme_color'] = '#222222';

                return $manifest;
            }
        };

        $payload = $this->manifestPayload([$first, $second]);

        self::assertSame('Brand', $payload['name']);
        self::assertSame('#222222', $payload['theme_color']);
        self::assertSame('Original', $payload['short_name']);
    }

    public function testManifestWorksWithoutProviders(): void
    {
        $payload = $this->manifestPayload([]);

        self::assertSame('Original', $payload['name']);
    }

    /**
     * @param iterable<ManifestOverlayProviderInterface> $providers
     *
     * @return array<string, mixed>
     */
    private function manifestPayload(iterable $providers): array
    {
        $controller = new PwaController(
            true,
            new ManifestBuilder(),
            new ServiceWorkerScriptBuilder(),
            ['name' => 'Original', 'short_name' => 'Original', 'start_url' => '/', 'absolute_start_url' => false],
            [],
            ['manifest_cache_max_age' => 0],
            ['head' => '', 'install_prompt' => '', 'offline' => ''],
            [],
            $providers,
        );

        $response = $controller->manifest(Request::create('/manifest.webmanifest'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getContent(), true);

        return $payload;
    }
}
