<?php

declare(strict_types=1);

namespace Haszi\Router\Test\UnitTest;

use Haszi\Router\Route;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    public function testRouteGetsCreated()
    {
        $route = new Route('', function () {});

        $this->assertInstanceOf(Route::class, $route);
    }

    public function testRouteReturnsCorrectPattern()
    {
        $route = new Route('\pattern1', function () {});

        $route2 = new Route('\pattern2', function () {});

        $this->assertSame($route->getPattern(), $route->getPattern());

        $this->assertNotSame($route2->getPattern(), $route->getPattern());
    }

    public function testRouteReturnsCorrectHandler()
    {
        $route = new Route('', function () {});

        $route2 = new Route('', function () {});

        $this->assertSame($route->getHandler(), $route->getHandler());

        $this->assertNotSame($route2->getHandler(), $route->getHandler());
    }
}
