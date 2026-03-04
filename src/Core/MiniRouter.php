<?php

declare(strict_types=1);

namespace MyCodebox\MiniRouter\Core;

use BadMethodCallException;
use Closure;
use MyCodebox\MiniRouter\Exceptions\MiniRouterException;
use MyCodebox\MiniRouter\Interfaces\MiniMiddlewareInterface;

/**
 * @method MiniRoute get(string $pattern, callable|array<string, mixed>|string $handler)
 * @method MiniRoute post(string $pattern, callable|array<string, mixed>|string $handler)
 * @method MiniRoute put(string $pattern, callable|array<string, mixed>|string $handler)
 * @method MiniRoute patch(string $pattern, callable|array<string, mixed>|string $handler)
 * @method MiniRoute delete(string $pattern, callable|array<string, mixed>|string $handler)
 * @method MiniRoute options(string $pattern, callable|array<string, mixed>|string $handler)
 * @method MiniRoute[] any(array<int, string> $methods, string $pattern, callable|array<string, mixed>|string $handler)
 */
class MiniRouter
{
    /**
     * @var array<int, MiniRoute>
     */
    protected array $routes = [];
    /**
     * @var array<int, callable|MiniMiddlewareInterface>
     */
    protected array $middleware = [];
    /**
     * @var array<int, MiniRouteGroup>
     */
    protected array $groups             = [];
    protected ?MiniContainer $container = null;
    protected ?MiniRoute $currentRoute  = null;
    protected bool $debug               = false;

    /**
     * MiniRouter constructor.
     */
    public function __construct(
        ?MiniContainer $container = null,
        bool $debug = false,
    ) {
        $this->container = $container;
        $this->debug     = $debug;
    }

    /**
     * Adds a route.
     *
     * @param callable|array<string, mixed>|string $handler
     */
    public function addRoute(
        string $method,
        string $pattern,
        callable|array|string $handler,
    ): MiniRoute {
        $routePattern = $pattern;
        $routeHandler = $handler;

        if (is_array($routeHandler)) {
            $filteredHandlerArray = [];

            foreach ($routeHandler as $key => $value) {
                if (is_string($key)) {
                    $filteredHandlerArray[$key] = $value;
                }
            }

            // If there are keys that are not strings, throw an error
            foreach (array_keys($filteredHandlerArray) as $key) {
                if (!is_string($key)) {
                    throw new \InvalidArgumentException(
                        'Handler array must have only string keys',
                    );
                }
            }
            $routeHandler = $filteredHandlerArray;
        }

        if (
            !(
                is_string($routeHandler) || is_callable($routeHandler) || (count($routeHandler) === 0 || array_keys($routeHandler) === array_filter(array_keys($routeHandler), 'is_string'))
            )
        ) {
            $routeHandler = '';
        }
        // Explicitly cast to array<string, mixed> if array
        /** @var array<string, mixed>|callable|string $finalHandler */
        $finalHandler   = $routeHandler;
        $route          = new MiniRoute($method, (string) $routePattern, $finalHandler);
        $this->routes[] = $route;

        return $route;
    }

    /**
     * Enables dynamic HTTP methods like $router->get(), $router->post(), etc.
     *
     * @param array<int, mixed> $args
     */
    public function __call(string $name, array $args): MiniRoute
    {
        return $this->registerHttpRoute($name, $args);
    }

    /**
     * Registers an HTTP route based on the method name.
     *
     * @param array<int, mixed> $args
     */
    private function registerHttpRoute(
        string $methodName,
        array $args,
    ): MiniRoute {
        $methodLower = strtolower($methodName);

        if (
            $methodLower === 'any' || in_array($methodLower, MiniUtils::HTTP_METHODS, true)
        ) {
            return $this->addRoute(strtoupper($methodName), ...$args);
        }

        throw new BadMethodCallException("Method $methodName does not exist");
    }

    /**
     * Registers a route for multiple HTTP methods.
     *
     * @param string|array<int, string> $methods
     * @param callable|array<string, mixed>|string $handler
     *
     * @return array<int, MiniRoute>
     */
    public function any($methods, string $pattern, $handler): array
    {
        $routesArray = [];

        foreach ((array) $methods as $httpMethod) {
            $upperMethod = strtoupper($httpMethod);

            if (
                !in_array(
                    strtolower($httpMethod),
                    MiniUtils::HTTP_METHODS,
                    true,
                )
            ) {
                continue;
            }
            $routesArray[] = $this->addRoute($upperMethod, $pattern, $handler);
        }

        return $routesArray;
    }

    /**
     * Creates a route group with a common prefix and/or middleware.
     */
    public function group(
        string $prefix,
        callable $groupCallback,
    ): MiniRouteGroup {
        $routeGroup = new MiniRouteGroup($prefix);
        $groupCallback($routeGroup);

        foreach ($routeGroup->getRoutes() as $route) {
            $this->routes[] = $route;
        }
        $this->groups[] = $routeGroup;

        return $routeGroup;
    }

    /**
     * Adds global middleware.
     */
    public function addMiddleware(mixed $m): self
    {
        MiniUtils::assertValidMiddleware($m);

        if (is_callable($m) || $m instanceof MiniMiddlewareInterface) {
            $this->middleware[] = $m;
        }

        return $this;
    }

    /**
     * Runs the middleware stack. $handler can be a callable or a string (container lookup).
     * Final callable receives ($req, $res, $args).
     *
     * @param array<int, callable|MiniMiddlewareInterface> $stack
     * @param callable|array<string, mixed>|string $handler
     * @param array<string, mixed> $args
     */
    private function runMiddlewareStack(
        array $stack,
        $handler,
        MiniRequest $req,
        MiniResponse $res,
        array $args = [],
    ): mixed {
        $final = function (MiniRequest $req, MiniResponse $res) use (
            $handler,
            $args,
        ) {
            $call = $handler;

            if (is_string($call)) {
                if ($this->container) {
                    if ($this->container->has($call)) {
                        $call = $this->container->get($call);
                    } else {
                        throw new MiniRouterException(
                            "Handler '$call' not in container.",
                        );
                    }
                } else {
                    throw new MiniRouterException(
                        "Handler '$call' is string but no container set.",
                    );
                }
            }

            if (!is_callable($call)) {
                throw new MiniRouterException('Handler is not callable.');
            }

            // Call handler with 2 or 3 args depending on handler signature
            try {
                $closure = Closure::fromCallable($call);
                $ref     = new \ReflectionFunction($closure);
                $num     = $ref->getNumberOfParameters();

                if ($num >= 3) {
                    return $call($req, $res, $args);
                }

                return $call($req, $res);
            } catch (\Throwable $e) {
                // If reflection fails for some callable type, fall back to calling with 3 args
                return $call($req, $res, $args);
            }
        };

        $next = $final;

        foreach (array_reverse($stack) as $middleware) {
            $prev = $next;
            $next = function (MiniRequest $req, MiniResponse $res) use (
                $middleware,
                $prev,
            ) {
                if (is_callable($middleware)) {
                    return $middleware($req, $res, $prev);
                }

                if ($middleware instanceof MiniMiddlewareInterface) {
                    return $middleware->process($req, $res, $prev);
                }

                // Unknown middleware type, skip to next
                return $prev($req, $res);
            };
        }

        return $next($req, $res);
    }

    /**
     * Generates a URL for a named route. Replaces {name} or {name:...} placeholders.
     *
     * @param array<string, mixed> $params
     */
    public function urlFor(string $name, array $params = []): ?string
    {
        foreach ($this->routes as $route) {
            if ($route->name === $name) {
                $urlPattern = is_string($route->pattern) ? $route->pattern : '';

                foreach ($params as $k => $v) {
                    $replaced = preg_replace(
                        "/\{" . preg_quote((string) $k, '/') . "(?::[^}]+)?\}/",
                        rawurlencode((string) $v),
                        $urlPattern,
                    );
                    $urlPattern = is_string($replaced) ? $replaced : '';
                }

                // $urlPattern is now either string or empty string, never mixed
                return $urlPattern;
            }
        }

        return null;
    }

    /**
     * Handles routing for the given HTTP method and URI.
     */
    protected function handleRoute(
        string $httpMethod,
        string $uri,
        MiniRequest $request,
        MiniResponse $response,
    ): MiniResponse {
        foreach ($this->routes as $route) {
            $patternRegex = MiniUtils::convertPattern($route->pattern);

            if (
                $route->method === strtoupper($httpMethod) && preg_match('#^' . $patternRegex . '$#', $uri, $matches)
            ) {
                $this->currentRoute = $route;
                $routeArguments     = MiniUtils::extractArgs($matches);

                foreach ($routeArguments as $attributeKey => $attributeValue) {
                    $request = $request->withAttribute(
                        $attributeKey,
                        $attributeValue,
                    );
                }
                $middlewareStack = $this->getMiddlewareStack($route);

                try {
                    $responseOrBody = $this->runMiddlewareStack(
                        $middlewareStack,
                        $route->handler,
                        $request,
                        $response,
                        $routeArguments,
                    );
                } catch (\Throwable $exception) {
                    return MiniUtils::errorResponse($exception, $this->debug);
                }

                return $responseOrBody instanceof MiniResponse
                    ? $responseOrBody
                    : $response->withBody($responseOrBody);
            }
        }

        $this->currentRoute = null;

        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody('404 Not Found');
    }

    /**
     * Returns the middleware stack for a given route.
     *
     * @return array<int, callable|MiniMiddlewareInterface>
     */
    protected function getMiddlewareStack(MiniRoute $route): array
    {
        /** @var array<int, callable|MiniMiddlewareInterface> $stack */
        $stack = array_merge(
            $this->middleware,
            $route->group ? $route->group->getMiddleware() : [],
            $route->middleware,
        );

        return $stack;
    }

    /**
     * Dispatches the current HTTP request.
     */
    public function dispatch(): void
    {
        $request = new MiniRequest(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI']    ?? '/',
            $_GET,
            MiniRequest::parseBody(),
            MiniRequest::getHeadersFallback(),
        );
        $response = new MiniResponse();
        $this->handleRoute(
            $request->method,
            $request->uri,
            $request,
            $response,
        )->send();
    }

    /**
     * Forwards to a different path/method inside the same router without sending the response.
     */
    public function forward(
        string $path,
        string $method = 'GET',
        ?MiniRequest $request = null,
        ?MiniResponse $response = null,
    ): MiniResponse {
        $request = $request ?? new MiniRequest(
                $method,
                $path,
                $_GET,
                MiniRequest::parseBody(),
                MiniRequest::getHeadersFallback(),
            );
        $request->method = $method;
        $request->uri    = $path;
        $response        = $response ?? new MiniResponse();

        return $this->handleRoute($method, $path, $request, $response);
    }

    /**
     * Returns the current matched route, or null if none.
     */
    public function getCurrentRoute(): ?MiniRoute
    {
        return $this->currentRoute;
    }
}
