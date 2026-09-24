<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Routing;

use Nowo\PwaBundle\Controller\PwaController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Stateless route loader — safe under FrankenPHP workers that keep the same
 * container when the kernel is not rebooted between requests (reset_kernel false).
 */
final class PwaRouteLoader extends Loader
{
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
        $collection = new RouteCollection();
        $controller = PwaController::class;

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
