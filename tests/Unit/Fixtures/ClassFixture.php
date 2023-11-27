<?php

namespace Haszi\Router\Test\UnitTest\Fixtures;

class ClassFixture
{
    public function greet(string $string): string
    {
        return 'Hello ' . $string . '!';
    }

    public static function staticGreet(string $string): string
    {
        return 'Hello ' . $string . '!';
    }
}
