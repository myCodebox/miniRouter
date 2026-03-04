<?php

declare(strict_types=1);

namespace MyCodebox\MiniRouter\Core;

use BadMethodCallException;
use Closure;
use MyCodebox\MiniRouter\Interfaces\MiniMiddlewareInterface;

/**
 * Groups multiple routes under a common prefix and/or middleware.
 *
 * @method MiniRoute get(string $pattern, callable|array<string, mixed>|string $handler)
 * @method MiniRoute post(string $pattern, callable|array<string, mixed>|string $handler)
 * @method MiniRoute put(string $pattern, callable|array<string, mixed>|string $handler)
 * @method MiniRoute patch(string $pattern, callable|array<string, mixed>|string $handler)
 * @method MiniRoute delete(string $pattern, callable|array<string, mixed>|string $handler)
 * @method MiniRoute options(string $pattern, callable|array<string, mixed>|string $handler)
 * @method MiniRoute[] any(string $pattern, callable|array<string, mixed>|string $handler, ...$args)
 */
class MiniRouteGroup
{
    public string $prefix;

    /** @var array<int, callable|MiniMiddlewareInterface> */
    public array $middleware = [];

    /** @var array<int, MiniRoute> */
    public array $routes = [];

    public function __construct(string $prefix = '')
    {
        $this->prefix = $prefix;
    }

    /**
     * Adds middleware to the group.
     *
     * @return $this
     */
    public function addMiddleware(
        callable|MiniMiddlewareInterface $middleware,
    ): self {
        MiniUtils::assertValidMiddleware($middleware);
        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * Adds a route to the group.
     *
     * @return $this
     */
    public function addRoute(MiniRoute $route): self
    {
        $route->setGroup($this);
        $this->routes[] = $route;

        return $this;
    }

    /**
     * Returns all routes of the group.
     *
     * @return array<int, MiniRoute>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Returns all middleware of the group.
     *
     * @return array<int, callable|MiniMiddlewareInterface>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Enables dynamic HTTP methods like $group->get(), $group->post(), etc.
     *
     * @param array<int, mixed> $args
     */
    public function __call(string $method, array $args): MiniRoute
    {
        $methodLower = strtolower($method);

        if (
            $methodLower === 'any' || in_array($methodLower, MiniUtils::HTTP_METHODS, true)
        ) {
            return $this->registerHttpRoute(strtoupper($method), ...$args);
        }

        throw new BadMethodCallException("Unknown method $method");
    }

    /**
     * Registers an HTTP route in the group.
     *
     * @param callable $handler
     * @param array<int, mixed> $args
     */
    public function registerHttpRoute(
        string $httpMethod,
        string $pattern,
        $handler,
        ...$args,
    ): MiniRoute {
        // Append prefix to the pattern if not already present
        $fullPattern = $this->prefix . $pattern;
        $route       = new MiniRoute($httpMethod, $fullPattern, $handler);

        if (!empty($args)) {
            foreach ($args as $arg) {
                if (
                    $arg instanceof Closure || is_callable($arg) || $arg instanceof MiniMiddlewareInterface
                ) {
                    $route->addMiddleware($arg);
                }
            }
        }
        $this->addRoute($route);

        return $route;
    }

    /**
     * Registers a route for all HTTP methods.
     *
     * @param callable $handler
     * @param array<int, mixed> $args
     *
     * @return MiniRoute[]
     */
    public function any(string $pattern, $handler, ...$args): array
    {
        $methods = array_map('strtoupper', MiniUtils::HTTP_METHODS);
        $routes  = [];

        foreach ($methods as $method) {
            $routes[] = $this->registerHttpRoute(
                $method,
                $pattern,
                $handler,
                ...$args,
            );
        }

        return $routes;
    }
}
