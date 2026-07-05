<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Routing;

use Nowo\PwaBundle\Controller\PwaController;
use RuntimeException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class PwaRouteLoader extends Loader
{
    private bool $loaded = false;

    /**
     * @param array<string, array{path: string, name: string}> $routes
     */
    public function __construct(
        private readonly array $routes,
        private readonly string $routePrefix,
    ) {
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new RuntimeException('PWA routes already loaded.');
        }

        $this->loaded = true;
        $collection   = new RouteCollection();
        $controller   = PwaController::class;

        /** @var array<string, array{0: string, 1: list<string>}> $map */
        $map = [
            'manifest'       => ['manifest', ['GET']],
            'service_worker' => ['serviceWorker', ['GET']],
            'offline'        => ['offline', ['GET']],
        ];

        foreach ($map as $key => [$action, $methods]) {
            $collection->add(
                $this->routes[$key]['name'],
                new Route(
                    $this->routePrefix . $this->routes[$key]['path'],
                    ['_controller' => $controller . '::' . $action],
                    [],
                    [],
                    '',
                    [],
                    $methods,
                ),
            );
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'nowo_pwa';
    }
}
