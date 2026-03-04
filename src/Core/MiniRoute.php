<?php

declare(strict_types=1);

namespace MyCodebox\MiniRouter\Core;

use MyCodebox\MiniRouter\Interfaces\MiniMiddlewareInterface;

class MiniRoute
{
    public string $method;
    public string $pattern;
    /** @var callable|array<string, mixed>|string */
    public $handler;
    public ?string $name = null;
    /** @var array<int, callable|MiniMiddlewareInterface> */
    public array $middleware      = [];
    public ?MiniRouteGroup $group = null;

    /**
     * @param callable|array<string, mixed>|string $handler
     */
    public function __construct(string $method, string $pattern, $handler)
    {
        $this->method  = $method;
        $this->pattern = $pattern;
        $this->handler = $handler;
    }

    /**
     * Sets the name of the route.
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets the name of the route.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Adds middleware to the route.
     *
     * @param callable|MiniMiddlewareInterface $middleware
     *
     * @return $this
     */
    public function addMiddleware($middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * Sets the group for the route.
     */
    public function setGroup(MiniRouteGroup $group): void
    {
        $this->group = $group;
    }
}
