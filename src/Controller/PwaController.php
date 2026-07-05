<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Controller;

use Nowo\PwaBundle\Service\ManifestBuilder;
use Nowo\PwaBundle\Service\ServiceWorkerScriptBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function sprintf;

final class PwaController extends AbstractController
{
    /**
     * @param array<string, mixed> $manifestConfig
     * @param array<string, mixed> $serviceWorkerConfig
     * @param array<string, mixed> $httpConfig
     * @param array{head: string, install_prompt: string, offline: string} $templates
     * @param array<string, array{path: string, name: string}> $routes
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly ManifestBuilder $manifestBuilder,
        private readonly ServiceWorkerScriptBuilder $serviceWorkerScriptBuilder,
        private readonly array $manifestConfig,
        private readonly array $serviceWorkerConfig,
        private readonly array $httpConfig,
        private readonly array $templates,
        private readonly array $routes,
    ) {
    }

    public function manifest(Request $request): JsonResponse
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException('PWA is disabled.');
        }

        $startUrlPath = (string) ($this->manifestConfig['start_url'] ?? '/');
        $absolute     = (bool) ($this->manifestConfig['absolute_start_url'] ?? true);
        $startUrl     = $absolute
            ? $request->getSchemeAndHttpHost() . $startUrlPath
            : $startUrlPath;

        $manifest = $this->manifestBuilder->build($this->manifestConfig, $startUrl);

        $response = new JsonResponse($manifest);
        $response->headers->set('Content-Type', 'application/manifest+json');
        $this->applyCacheHeaders(
            $response,
            (int) ($this->httpConfig['manifest_cache_max_age'] ?? 3600),
            (bool) ($this->httpConfig['manifest_public_cache'] ?? true),
        );

        return $response;
    }

    public function serviceWorker(Request $request): Response
    {
        if (!$this->enabled || !($this->serviceWorkerConfig['enabled'] ?? true)) {
            throw $this->createNotFoundException('Service worker is disabled.');
        }

        $offlinePath = (string) ($this->routes['offline']['path'] ?? '/offline');
        $offlineUrl  = $request->getSchemeAndHttpHost() . $offlinePath;
        $script      = $this->serviceWorkerScriptBuilder->build($this->serviceWorkerConfig, $offlineUrl);

        $response = new Response($script, Response::HTTP_OK, [
            'Content-Type'           => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => (string) ($this->serviceWorkerConfig['scope'] ?? '/'),
        ]);

        $this->applyCacheHeaders(
            $response,
            (int) ($this->httpConfig['service_worker_cache_max_age'] ?? 0),
            false,
        );

        return $response;
    }

    public function offline(): Response
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException('PWA is disabled.');
        }

        return $this->render($this->templates['offline'], [
            'manifest_name' => (string) ($this->manifestConfig['name'] ?? 'App'),
        ]);
    }

    private function applyCacheHeaders(Response $response, int $maxAge, bool $public): void
    {
        if ($maxAge <= 0) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

            return;
        }

        $visibility = $public ? 'public' : 'private';
        $response->headers->set('Cache-Control', sprintf('%s, max-age=%d', $visibility, $maxAge));
    }
}
