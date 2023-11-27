<?php

declare(strict_types=1);

namespace Haszi\Router;

use Closure;

class Route
{
    private string $pattern = '';

    private Closure $handler;

    public function __construct(string $pattern, Closure $handler)
    {
        $this->pattern = $pattern;
        $this->handler = $handler;
    }

    /**
     * Returns the route's pattern
     *
     * @return string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Returns the route's handler
     *
     * @return \Closure
     */
    public function getHandler(): \Closure
    {
        return $this->handler;
    }
}
