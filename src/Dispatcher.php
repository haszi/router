<?php

declare(strict_types=1);

namespace Haszi\Router;

use Haszi\Router\Exceptions\RouteNotFoundException;
use Haszi\Router\Router;

use function count;

class Dispatcher
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function dispatch(string $httpMethod, string $uri): mixed
    {
        $beforeRouteMiddleware = $this->router->getBeforeMiddleware($httpMethod, $uri);

        foreach ($beforeRouteMiddleware as $routes) {
            ($routes['route']->getHandler())();
        }

        $matchedRoutes = $this->router->match($httpMethod, $uri);

        if (count($matchedRoutes) === 0) {
            return $this->handleRouteNotFound($httpMethod, $uri);
        }

        return ($matchedRoutes[0]['route']->getHandler())(...$matchedRoutes[0]['params']);
    }

    /**
     * Executes the handler registered for when a route is not found
     *
     * @param string $httpMethod    HTTP method/verb of the route
     * @param string $uri           URI of the route
     *
     * @return mixed
     */
    private function handleRouteNotFound(string $httpMethod, string $uri): mixed
    {
        $routeNotFoundHandler = $this->router->getRouteNotFoundHandler();

        if (!isset($routeNotFoundHandler)) {
            throw new RouteNotFoundException('Route \'' . $httpMethod . '\' \'' . $uri . '\' not found.');
        }

        return ($routeNotFoundHandler)();
    }
}
