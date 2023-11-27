<?php

declare(strict_types=1);

namespace Haszi\Router\Test\UnitTest;

use Haszi\Router\Exceptions\InvalidRouteException;
use Haszi\Router\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private ?Router $router;

    public function setUp(): void
    {
        $this->router = new Router();
    }

    public function testRouterGetsCreated()
    {
        $this->assertInstanceOf(Router::class, $this->router);
    }

    /**
     * @dataProvider invalidHttpMethods
     */
    public function testRouteThrowsOnInvalidHttpMethod($invalidHttpMethod)
    {
        $this->expectException(InvalidRouteException::class);

        $this->router->addRoute($invalidHttpMethod, '/about-us/', function () {});
    }

    public static function invalidHttpMethods()
    {
        return [
            [''],
            ['UNKNOWN_METHOD'],
            [[]],
            [['']],
            [['UNKNOWN_METHOD']]
        ];
    }

    /**
     * @dataProvider validRouteHttpMethods
     */
    public function testRouteAcceptsValidHttpMethod($validRequestMethod)
    {
        $this->expectNotToPerformAssertions();

        $this->router->addRoute($validRequestMethod, '/about-us/', function () {});
    }

    public static function validRouteHttpMethods()
    {
        return [
            ['*'],
            ['GET'],
            ['get'],
            ['HEAD'],
            ['head'],
            ['POST'],
            ['PUT'],
            ['DELETE'],
            ['PATCH'],
            ['OPTIONS']
        ];
    }

    /**
     * @dataProvider validHttpMethods
     */
    public function testRouteExpandAsteriksToValidHttpMethods($httpMethod)
    {
        $this->router->addRoute('*', '/about-us/', function () {});

        $this->assertNotEmpty($this->router->match($httpMethod, '/about-us/'));
    }

    public static function validHttpMethods()
    {
        return [
            ['GET'],
            ['HEAD'],
            ['POST'],
            ['PUT'],
            ['DELETE'],
            ['PATCH'],
            ['OPTIONS']
        ];
    }

    public function testRouteMethodArrayGetsExpandedCorrectly()
    {
        $methods = ['GET','HEAD'];

        $this->router->addRoute($methods, '/about-us/', function () {});

        foreach ($methods as $method) {
            $this->assertNotEmpty($this->router->match($method, '/about-us/'));
        }
    }

    public function testRouteThrowsOnInvalidHandlerStringFormat()
    {
        $this->expectException(InvalidRouteException::class);

        $this->router->addRoute('POST', '/uri/', 'className-methodName');
    }

    public function testRouteAcceptsValidHandlerStringFormat()
    {
        $this->expectNotToPerformAssertions();

        $this->router->addRoute('PUT', '/uri/', 'className@methodName');
    }

    public function testRouteThrowsOnAlreadyDefinedRoute()
    {
        $this->router->addRoute('PUT', '/uri/', 'className@methodName');

        $this->expectException(InvalidRouteException::class);

        $this->router->addRoute('PUT', '/uri/', 'className@methodName');
    }

    /**
     * @dataProvider requestMethodUriAndNonMatchingRoutes
     */
    public function testMatchReturnsEmptyArrayOnUnknowRoutes($httpMethod, $uri, $handler, $nonMatchingRoutes)
    {
        foreach ($nonMatchingRoutes as $nonMatchingRoute) {
            $this->router->addRoute(
                $nonMatchingRoute['httpMethod'],
                $nonMatchingRoute['uri'],
                $nonMatchingRoute['handler']
            );
        }

        $matches = $this->router->match($httpMethod, $uri);

        $this->assertSame([], $matches);
    }

    /**
     * @dataProvider requestMethodUriAndNonMatchingRoutes
     */
    public function testMatchReturnsRoutes($httpMethod, $uri, $handler, $nonMatchingRoutes)
    {
        foreach ($nonMatchingRoutes as $nonMatchingRoute) {
            $this->router->addRoute(
                $nonMatchingRoute['httpMethod'],
                $nonMatchingRoute['uri'],
                $nonMatchingRoute['handler']
            );
        }

        $this->router->addRoute($httpMethod, $uri, $handler);

        $matches = $this->router->match($httpMethod, $uri);

        foreach ($matches as $match) {
            $this->assertSame($handler, $match['route']->getHandler());
        }
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
            [
                'httpMethod' => 'put',
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

    public function testNotFoundHandlerGetsRegisteredAndReturned()
    {
        $expectedMessage = 'Route not found';
        $this->router->setRouteNotFoundHandler(
            function () use ($expectedMessage) { return $expectedMessage; }
        );

        $handler = $this->router->getRouteNotFoundHandler();

        $this->assertSame($expectedMessage, $handler());
    }

    /**
     * @dataProvider prefixesAndRoutes
     */
    public function testGroupAppliesPrefixToAllRoutesInCallback($prefix, $routes, $expectedRoutes)
    {
        $this->router->group($prefix, function ($router) use ($routes) {
            foreach ($routes as $route) {
                $router->addRoute(
                    $route['httpMethod'],
                    $route['route'],
                    $route['handler']
                );
            }
        });

        foreach ($expectedRoutes as $route) {
            $this->assertNotEmpty(
                $this->router->match(
                    $route['httpMethod'],
                    $route['route']
                )
            );
        }

    }

    public static function prefixesAndRoutes()
    {
        return [
            [
                'prefix' => '/myprefix',
                'routes' => [
                    [
                        'httpMethod' => 'GET',
                        'route' => '/login',
                        'handler' => function () {}
                    ],
                    [
                        'httpMethod' => 'POST',
                        'route' => '/users',
                        'handler' => function () {}
                    ],
                ],
                'expectedRoutes' => [
                    [
                        'httpMethod' => 'GET',
                        'route' => '/myprefix/login',
                        'handler' => function () {}
                    ],
                    [
                        'httpMethod' => 'POST',
                        'route' => '/myprefix/users',
                        'handler' => function () {}
                    ],
                ],
            ]
        ];
    }

    /**
     * @dataProvider invalidHttpMethods
     */
    public function testBeforeThrowsOnInvalidHttpMethod($invalidHttpMethod)
    {
        $this->expectException(InvalidRouteException::class);

        $this->router->before($invalidHttpMethod, '/about-us/', function () {});
    }

    /**
     * @dataProvider httpMethodUriAndMiddlewareReturnValue
     */
    public function testSetAndGetBeforeRouteMiddleware($httpMethod, $pattern, $uri, $middlewareExpectedReturnValue)
    {
        $this->router->before(
            $httpMethod,
            $pattern,
            function () use ($middlewareExpectedReturnValue) {
                return $middlewareExpectedReturnValue;
        });

        $beforeRouteMiddleware = $this->router->getBeforeMiddleware($httpMethod, $uri);

        $this->assertNotEmpty($beforeRouteMiddleware);

        foreach ($beforeRouteMiddleware as $middleware) {
            $middlewareResult = ($middleware['route']->getHandler())();

            $this->assertSame($middlewareExpectedReturnValue, $middlewareResult);
        }
    }

    public static function httpMethodUriAndMiddlewareReturnValue()
    {
        return [
            [
                'httpMethod' => 'GET',
                'pattern' => '/users',
                'uri' => '/users',
                'expectedReturnValue' => 'Run before returning user list'
            ],
            [
                'httpMethod' => 'GET',
                'pattern' => '/users/{name}',
                'uri' => '/users/eva',
                'expectedReturnValue' => 'Run before returning user list'
            ],
        ];
    }

    public function testReturnsEmptyArrayForNonExistentBeforeRouteMiddleware()
    {
        $this->router->before('GET', '/login', function () {});

        $beforeRouteMiddleware = $this->router->getBeforeMiddleware(
            'GET', '/non-existent-uri'
        );

        $this->assertEmpty($beforeRouteMiddleware);
    }

    /**
     * @dataProvider    routesWithQueryOrFragment
     */
    public function testQueryAndFragmentInUriAreIgnored($httpMethod, $pattern, $handler, $matchingUriWithQueryOrFragment)
    {
        $this->router->addRoute($httpMethod, $pattern, $handler);

        foreach ($matchingUriWithQueryOrFragment as $uri) {
            $this->assertNotEmpty($this->router->match($httpMethod, $uri));
        }
    }

    public static function routesWithQueryOrFragment()
    {
        return [
            [
                'httpMethod' => 'GET',
                'pattern' => 'host/path/to/resource',
                'handler' => fn () => '',
                'matchingUriWithQueryOrFragment' => [
                    'host/path/to/resource?query=1',
                    'host/path/to/resource#fragment',
                    'host/path/to/resource?query=1#fragment'
                ]
            ],
        ];
    }

    /**
     * @dataProvider    patternWithMatchingUris
     */
    public function testPatternUriMatching($httpMethod, $pattern, $handler, $matchingUris)
    {
        $this->router->addRoute($httpMethod, $pattern, $handler);

        foreach ($matchingUris as $matchingUri) {
            $this->assertNotEmpty($this->router->match($httpMethod, $matchingUri));
        }
    }

    public static function patternWithMatchingUris()
    {
        return [
            [
                'httpMethod' => 'GET',
                'pattern' => 'users/(\d+)',
                'handler' => fn () => '',
                'matchingUris' =>[
                    'users/12345',
                    'users/987654',
                ],
                'httpMethod' => 'GET',
                'pattern' => 'users/(\w+)',
                'handler' => fn () => '',
                'matchingUris' =>[
                    'users/a',
                    'users/9',
                    'users/zoltan',
                ],
                'httpMethod' => 'GET',
                'pattern' => 'users(/\w+)?',
                'handler' => fn () => '',
                'matchingUris' =>[
                    'users/z',
                    'users/',
                ],
                'httpMethod' => 'GET',
                'pattern' => 'users/{id}',
                'handler' => fn () => '',
                'matchingUris' =>[
                    'users/abcd',
                    'users/123',
                ],
                'httpMethod' => 'GET',
                'pattern' => 'users/{id}?',
                'handler' => fn () => '',
                'matchingUris' =>[
                    'users/abcd',
                    'users/123',
                    'users',
                ],
                'httpMethod' => 'GET',
                'pattern' => 'users/{id}/profile',
                'handler' => fn () => '',
                'matchingUris' =>[
                    'users/xyz/profile',
                    'users/987/profile',
                ],
                'httpMethod' => 'GET',
                'pattern' => 'artist/{artistId}/album/{albumId}',
                'handler' => fn () => '',
                'matchingUris' =>[
                    'artist/1234/album/9876',
                    'artist/555/album/666',
                ],
            ],
        ];
    }

    /**
     * @dataProvider    patternWithNonMatchingUris
     */
    public function testPatternWithNonMatchingUri($httpMethod, $pattern, $handler, $nonMatchingUris)
    {
        $this->router->addRoute($httpMethod, $pattern, $handler);

        foreach ($nonMatchingUris as $nonMatchingUri) {
            $this->assertEmpty($this->router->match($httpMethod, $nonMatchingUri));
        }
    }

    public static function patternWithNonMatchingUris()
    {
        return [
            [
                'httpMethod' => 'GET',
                'pattern' => 'users/(\d+)',
                'handler' => fn () => '',
                'matchingUris' =>[
                    'users/1/a',
                    'users/90876/1',
                    'users/adam',
                ],
                'httpMethod' => 'GET',
                'pattern' => 'users/(\w+)',
                'handler' => fn () => '',
                'matchingUris' =>[
                    'users/a/1',
                    'users/1/a',
                    'users/apple/a',
                    'users/',
                ],
                'httpMethod' => 'GET',
                'pattern' => 'users/{id}',
                'handler' => fn () => '',
                'matchingUris' =>[
                    'users/',
                ],
                'httpMethod' => 'GET',
                'pattern' => 'users/{id}/profile',
                'handler' => fn () => '',
                'matchingUris' =>[
                    'users/profile',
                    'users/123/profile/settings',
                ]
            ],
        ];
    }

    public function testClassMethodStringStaticGetsConvertedToClosure()
    {
        $this->router->addRoute(
            'GET',
            '/movies',
            'Haszi\Router\Test\UnitTest\Fixtures\ClassFixture@staticGreet'
        );

        $matchedRoutes = $this->router->match('GET', '/movies');

        $handler = $matchedRoutes[0]['route']->getHandler();

        $this->assertSame('Hello World!', $handler('World'));
    }

    public function testClassMethodStringNonStaticGetsConvertedToClosure()
    {
        $this->router->addRoute(
            'GET',
            '/movies',
            'Haszi\Router\Test\UnitTest\Fixtures\ClassFixture@greet'
        );

        $matchedRoutes = $this->router->match('GET', '/movies');

        $handler = $matchedRoutes[0]['route']->getHandler();

        $this->assertSame('Hello World!', $handler('World'));
    }
}
