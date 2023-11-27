<?php

declare(strict_types=1);

namespace Haszi\Router;

use Closure;
use Haszi\Router\Exceptions\InvalidRouteException;
use Haszi\Router\Route;
use ReflectionMethod;

use function array_map;
use function count;
use function explode;
use function in_array;
use function is_string;
use function preg_match;
use function strpos;
use function substr;
use function strtoupper;
use function trim;

class Router
{
    private const ALLOWED_HTTP_METHODS = [
        'GET',
        'HEAD',
        'POST',
        'PUT',
        'DELETE',
        'PATCH',
        'OPTIONS'
    ];

    /** @var array<string, array<string, Route>> */
    private array $routes = [];

    private ?Closure $routeNotFoundHandler = null;

    private string $groupPrefix = '';

    /** @var array<string, array<string, array<Route>>> */
    private array $beforeRoutes = [];

    /**
     * Adds a route to the collection
     *
     * @param string|array<string> $httpMethod  HTTP method of the route
     * @param string $pattern                   URI pattern of the route
     * @param string|Closure $handler           Route handler
     *
     * @throws InvalidRouteException            On invalid HTTP method,
     *                                          URI or already defined route
     */
    public function addRoute(
        string|array $httpMethod,
        string $pattern,
        string|Closure $handler
    ): void
    {
        $httpMethods = $this->normalizeHttpMethodToArray($httpMethod);

        if ($httpMethods === []) {
            throw new InvalidRouteException('Unknown HTTP method \'\'.');
        }

        $fullPattern = trim(trim($pattern), '/');
        if ($this->groupPrefix !== '') {
            $fullPattern = $this->groupPrefix . '/' . $fullPattern;
        }

        foreach ($httpMethods as $method) {

            if (!in_array($method, self::ALLOWED_HTTP_METHODS)) {
                throw new InvalidRouteException('Unknown HTTP method \'' . $method . '\'.');
            }

            if (isset($this->routes[$method][$fullPattern])) {
                throw new InvalidRouteException(
                    'Route already defined: \''. $method . '\' \'' . $fullPattern . '\'.'
                );
            }

            $this->routes[$method][$fullPattern] = new Route(
                $fullPattern,
                $this->normalizeHandler($handler)
            );
        }
    }

    /**
     * Returns an array of HTTP methods from a string or an array
     *
     * @param string|array<string> $httpMethod  HTTP method of the route
     *
     * @return array<string>
     */
    private function normalizeHttpMethodToArray(string|array $httpMethod): array
    {
        if ($httpMethod === '*') {
            return self::ALLOWED_HTTP_METHODS;
        }

        if (is_string($httpMethod)) {
            $httpMethod = [$httpMethod];
        }

        $httpMethod = array_map(
            fn ($method) => strtoupper(trim($method)),
            $httpMethod
        );

        return $httpMethod;
    }

    /**
     * Normalizes handlers by constructing a Closure from them
     *
     * @param string|Closure $handler  Handler to be normalized
     *
     * @return Closure
     *
     * @throws InvalidRouteException    String handler not in the 'class@method' format
     */
    private function normalizeHandler(string|Closure $handler) : Closure
    {
        if ($handler instanceof Closure) {
            return $handler;
        }

        $handlerClassAndMethod = explode('@', $handler);

        if (count($handlerClassAndMethod) !== 2) {
            throw new InvalidRouteException(
                'Handler must be a Closure or a string in the \'class@method\' format.'
            );
        }

        $class = $handlerClassAndMethod[0];
        $method = $handlerClassAndMethod[1];

        $handlerClosure = function (...$args) use ($class, $method): mixed
        {
            $reflectionMethod = new ReflectionMethod($class, $method);

            if ($reflectionMethod->isStatic()
                && ! $reflectionMethod->isAbstract()
                && $reflectionMethod->isPublic()) {

                return $class::$method(...$args);
            }

            return (new $class())->$method(...$args);
        };

        return $handlerClosure;
    }

    /**
     * Returns all registered routes for a given HTTP method and URI
     *
     * @param string $httpMethod    HTTP method of the route
     * @param string $uri           URI of the route
     *
     * @return array<int, array{route:Route, params:array<mixed> } >
     */
    public function match(string $httpMethod, string $uri): array
    {
        $httpMethod = strtoupper(trim($httpMethod));

        if (!isset($this->routes[$httpMethod])
            || !in_array($httpMethod, self::ALLOWED_HTTP_METHODS)) {
            return [];
        }

        $matchedRoutes = [];

        $uriPattern = $this->getCleanUri($uri);


        foreach ($this->routes[$httpMethod] as $pattern => $route) {

            $pattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);

            if (preg_match('#^' . $pattern . '$#', $uriPattern, $matches)) {
                $params = array_slice($matches, 1);
                $matchedRoutes[] = [
                    'route' => $route,
                    'params' => $params
                ];
            }
        }

        return $matchedRoutes;
    }

    /**
     * Removes query and fragment, and leading/trailing slashes from uri string
     *
     * @param string $uri   URI
     *
     * @return string
     */
    private function getCleanUri(string $uri): string
    {
        $uri = trim($uri);

        $queryParamPos = strpos($uri, '?');
        if ($queryParamPos !== false) {
            $uri = substr($uri, 0, $queryParamPos);
        }

        $fragmentPos = strpos($uri, '#');
        if ($fragmentPos !== false) {
            $uri = substr($uri, 0, $fragmentPos);
        }

        return trim(trim($uri), '/');
    }

    /**
     * Shorthand for adding a route to any method
     */
    public function any(string $uri, string|Closure $handler): void
    {
        $this->addRoute('*', $uri, $handler);
    }

    /**
     * Shorthand for adding a route using GET
     */
    public function get(string $uri, string|Closure $handler): void
    {
        $this->addRoute('GET', $uri, $handler);
    }

    /**
     * Shorthand for adding a route using HEAD
     */
    public function head(string $uri, string|Closure $handler): void
    {
        $this->addRoute('HEAD', $uri, $handler);
    }

    /**
     * Shorthand for adding a route using POST
     */
    public function post(string $uri, string|Closure $handler): void
    {
        $this->addRoute('POST', $uri, $handler);
    }

    /**
     * Shorthand for adding a route using PUT
     */
    public function put(string $uri, string|Closure $handler): void
    {
        $this->addRoute('PUT', $uri, $handler);
    }

    /**
     * Shorthand for adding a route using PATCH
     */
    public function patch(string $uri, string|Closure $handler): void
    {
        $this->addRoute('PATCH', $uri, $handler);
    }

    /**
     * Shorthand for adding a route using DELETE
     */
    public function delete(string $uri, string|Closure $handler): void
    {
        $this->addRoute('DELETE', $uri, $handler);
    }

    /**
     * Shorthand for adding a route using OPTIONS
     */
    public function options(string $uri, string|Closure $handler): void
    {
        $this->addRoute('OPTIONS', $uri, $handler);
    }

    /**
     * Sets the handler that will be called when a route is not found during dispatch
     *
     * @param string|Closure $handler  Handler to call when a route is not found
     */
    public function setRouteNotFoundHandler(string|Closure $handler): void
    {
        $this->routeNotFoundHandler = $this->normalizeHandler($handler);
    }

    /**
     * Returns the handler that will be called when a route is not found during dispatch
     *
     * @return ?Closure
     */
    public function getRouteNotFoundHandler(): ?Closure
    {
        return $this->routeNotFoundHandler;
    }

    /**
     * Groups a set of calls by adding the group prefix to each route in the passed in callback
     *
     * @param string $groupPrefix   Group prefix to add to each route in the callback
     * @param Closure $callback    Callback that will have each route the group prefix applied to
     */
    public function group(string $groupPrefix, Closure $callback): void
    {
        $currentGroupPrefix = $this->groupPrefix;

        if ($this->groupPrefix !== '') {
            $this->groupPrefix .= '/';
        }

        $this->groupPrefix .= trim(trim($groupPrefix), '/');

        $callback($this);

        $this->groupPrefix = $currentGroupPrefix;
    }

    /**
     * Adds a callback to the list of 'before route' middleware
     *
     * @param string|array<string> $httpMethod  HTTP method of the route
     * @param string $uri                       URI of the route
     * @param string|Closure $handler          Handler to call before dispatching to route
     *
     * @throws InvalidRouteException            On invalid HTTP method, URI or already defined route
     */
    public function before(string|array $httpMethod, string $uri, string|Closure $handler): void
    {
        $httpMethod = $this->normalizeHttpMethodToArray($httpMethod);

        if ($httpMethod === []) {
            throw new InvalidRouteException('Unknown HTTP method \'\'  in \'before\' route.');
        }

        $fullPattern = trim(trim($uri), '/');
        if ($this->groupPrefix !== '') {
            $fullPattern = $this->groupPrefix . '/' . $fullPattern;
        }

        foreach ($httpMethod as $method) {
            if (!in_array($method, self::ALLOWED_HTTP_METHODS)) {
                throw new InvalidRouteException(
                    'Unknown HTTP method \'' . $method . '\' in \'before\' route.'
                );
            }

            $route = new Route(
                $fullPattern,
                $this->normalizeHandler($handler)
            );

            $this->beforeRoutes[$method][$fullPattern][] = $route;
        }
    }

    /**
     * Returns the before route middleware registered for an HTTP method and URI
     *
     * @param string $httpMethod
     * @param string $uri
     *
     * @return array<int, array{route:Route, params:array<mixed> } >
     */
    public function getBeforeMiddleware(string $httpMethod, string $uri): array
    {
        $httpMethod = strtoupper(trim($httpMethod));

        if (!isset($this->beforeRoutes[$httpMethod])) {
            return [];
        }

        $uriPattern = $this->getCleanUri($uri);

        $matchedRoutes = [];

        foreach ($this->beforeRoutes[$httpMethod] as $pattern => $routes) {
            foreach ($routes as $route) {

                $cleanPattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);

                if (preg_match('#^' . $cleanPattern . '$#', $uriPattern, $matches)) {
                    $params = array_slice($matches, 1);
                    $matchedRoutes[] = [
                        'route' => $route,
                        'params' => $params
                    ];
                }
            }
        }

        return $matchedRoutes;
    }
}
