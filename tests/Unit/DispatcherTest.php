<?php

declare(strict_types=1);

namespace Haszi\Router\Test\UnitTest;

use Haszi\Router\Dispatcher;
use Haszi\Router\Exceptions\RouteNotFoundException;
use Haszi\Router\Router;
use PHPUnit\Framework\TestCase;

final class DispatcherTest extends TestCase
{
    public function testDispatcherGetsCreated()
    {
        $router = new Router();
        $dispatcher = new Dispatcher($router);

        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
    }

    /**
     * @dataProvider requestMethodUriAndNonMatchingRoutes
     */
    public function testDispatchThrowsOnUnknownRoute($httpMethod, $uri, $nonMatchingRoutes)
    {
        $router = new Router();

        foreach ($nonMatchingRoutes as $nonMatchingRoute) {
            $router->addRoute(
                $nonMatchingRoute['httpMethod'],
                $nonMatchingRoute['uri'],
                $nonMatchingRoute['handler']
            );
        }

        $dispatcher = new Dispatcher($router);

        $this->expectException(RouteNotFoundException::class);

        $dispatcher->dispatch($httpMethod, $uri);
    }

    public static function requestMethodUriAndNonMatchingRoutes()
    {
        $sharedHandler = function () {};
        return [
            [
                'httpMethod' => 'GET',
                'uri' => '/products/',
                'handler' => $sharedHandler,
                'nonMatchingRoutes' => [
                    [
                        'httpMethod' => 'POST',
                        'uri' => '/products/',
                        'handler' => $sharedHandler
                    ],
                    [
                        'httpMethod' => 'GET',
                        'uri' => '/users/',
                        'handler' => $sharedHandler
                    ]
                ],
            ],
        ];
    }

    /**
     * @dataProvider requestMethodUriMatchingAndNonMatchingRoutes
     */
    public function testDispatchCallsFirstHandlerOnKnownRoute(
        $httpMethod,
        $uri,
        $handlerResult,
        $matchingRoutes,
        $nonMatchingRoutes)
    {
        $router = new Router();

        foreach ($nonMatchingRoutes as $nonMatchingRoute) {
            $router->addRoute(
                $nonMatchingRoute['httpMethod'],
                $nonMatchingRoute['uri'],
                $nonMatchingRoute['handler']
            );
        }

        foreach ($matchingRoutes as $matchingRoute) {
            $router->addRoute(
                $matchingRoute['httpMethod'],
                $matchingRoute['uri'],
                $matchingRoute['handler']
            );
        }

        $dispatcher = new Dispatcher($router);

        $result = $dispatcher->dispatch($httpMethod, $uri);

        $this->assertSame($handlerResult, $result);
    }


    public static function requestMethodUriMatchingAndNonMatchingRoutes()
    {
        $sharedHandler = function () { return 'Shared result'; };
        $sharedReturnValue = $sharedHandler();
        return [
            [
                'httpMethod' => 'GET',
                'uri' => '/products/',
                'handlerResult' => $sharedReturnValue,
                'matchingRoutes' => [
                    [
                        'httpMethod' => 'GET',
                        'uri' => '/products/',
                        'handler' => $sharedHandler
                    ]
                ],
                'nonMatchingRoutes' => [
                    [
                        'httpMethod' => 'POST',
                        'uri' => '/products/',
                        'handler' => $sharedHandler
                    ],
                    [
                        'httpMethod' => 'GET',
                        'uri' => '/users/',
                        'handler' => $sharedHandler
                    ]
                ],
            ],
        ];
    }

    public function testBeforeRouteMiddlewareGetExecutedBeforeRouting()
    {
        $router = new Router();

        $router->addRoute('GET', '/users', function () {
            echo 'Route dispatched';
        });

        $router->before('GET', '/users', function () {
            echo 'Before route callback #1 - ';
        });

        $router->before('GET', '/users', function () {
            echo 'Before route callback #2 - ';
        });

        $dispatcher = new Dispatcher($router);

        $this->expectOutputString(
            'Before route callback #1 - Before route callback #2 - Route dispatched'
        );

        $dispatcher->dispatch('GET', '/users');
    }

    public function testClosureHandlerIsPassedAppropriateParameters()
    {
        $router = new Router();

        $router->addRoute(
            'GET',
            'users/{name}/greet',
            function ($name) { echo 'Hello ' . $name . '!'; }
        );

        $dispatcher = new Dispatcher($router);

        $this->expectOutputString('Hello World!');

        $dispatcher->dispatch('GET', 'users/World/greet');
    }

    public function testClassMethodStringHandlerIsPassedAppropriateParameters()
    {
        $router = new Router();

        $router->addRoute(
            'GET',
            'users/{name}/greet',
            'Haszi\Router\Test\UnitTest\Fixtures\ClassFixture@greet'
        );

        $dispatcher = new Dispatcher($router);

        $this->assertSame('Hello World!', $dispatcher->dispatch('GET', 'users/World/greet'));
    }
}
