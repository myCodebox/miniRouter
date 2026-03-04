# MiniRouter

A minimalist, flexible PHP router for small and medium-sized projects.  
**Features:** Named routes, middleware, groups, reverse routing, dependency injection, flexible response types, complex placeholders, forwarding, and more.

---

## Table of Contents

1. [Overview & Features](#overview--features)
2. [Installation](#installation)
3. [Quick Start](#quick-start)
4. [Routing & Placeholders](#routing--placeholders)
5. [Middleware](#middleware)
    - [Global Middleware](#global-middleware)
    - [Route Middleware](#route-middleware)
    - [Group Middleware](#group-middleware)
    - [Middleware as a Class](#middleware-as-a-class)
6. [Container](#container)
    - [Service Variants with MiniContainer](#service-variants-with-minicontainer)
7. [Groups & API](#groups--api)
8. [Request & Response](#request--response)
9. [Reverse Routing](#reverse-routing)
10. [Error Handling](#error-handling)
11. [Complete Demo](#complete-demo)
12. [API Overview](#api-overview)
13. [License & Author](#license--author)

---

## Overview & Features

- Routing for all HTTP methods (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `HEAD`)
- Placeholders & complex patterns (`/user/{id:\d+}`)
- Named routes & reverse routing (`urlFor`)
- Middleware (global, per route, per group, as class)
- Route groups with prefix and shared middleware
- Dependency injection via MiniContainer
- Automatic body parsing (JSON, form data)
- Flexible response types (text, JSON, HTML, download)
- Server-side forwarding
- Error handling & debug mode

---

## Installation

1. Copy the files from `src/` into your project.
2. Install Composer dependencies (if needed).
3. Create an entry point, e.g., `index.php`, or use the example `example/demo.php`.

---

## Quick Start

> **Tip:**  
> MiniRouter supports middleware and dependency injection via container.  
> See examples in the next section.

### Option 1: With Composer (recommended)

1. Install dependencies (only needed once):

```sh
composer require mycodebox/minirouter
```

2. Example code:

```php
require_once __DIR__ . '/vendor/autoload.php';

use MyCodebox\MiniRouter\Core\MiniRouter;

$router = new MiniRouter();
$router->get('/hello/{name}', function ($req, $res, $args) {
    return $res->withHeader('Content-Type', 'text/plain')
                ->withBody('Hello, ' . $args['name'] . '!');
});
$router->dispatch();
```

### Option 2: Without Composer (direct include)

```php
require_once __DIR__ . '/src/Core/MiniRouter.php';

use MyCodebox\MiniRouter\Core\MiniRouter;

$router = new MiniRouter();
$router->get('/hello/{name}', function ($req, $res, $args) {
    return $res->withHeader('Content-Type', 'text/plain')
               ->withBody('Hello, ' . $args['name'] . '!');
});
$router->dispatch();
```

---

## Middleware

MiniRouter supports different types of middleware:

### Global Middleware

```php
$router->addMiddleware(function ($req, $res, $next) {
    // Logging, authentication, etc.
    return $next($req, $res);
});
```

### Route Middleware

```php
$router->get('/secure', $handler)
       ->addMiddleware(function ($req, $res, $next) {
           // Route-specific logic
           return $next($req, $res);
       });
```

### Group Middleware

```php
$router->group('/api', function ($group) {
    $group->addMiddleware(function ($req, $res, $next) {
        // Group-specific logic
        return $next($req, $res);
    });
    $group->get('/user/{id}', $handler);
});
```

### Middleware as a Class

```php
class ApiKeyMiddleware implements MiniMiddlewareInterface {
    public function process($req, $res, $next) {
        // Class-based middleware logic
        return $next($req, $res);
    }
}
$router->addMiddleware(new ApiKeyMiddleware());
```

---

## Container

Use the built-in container for dependency injection:

```php
use MyCodebox\MiniRouter\Core\MiniContainer;
$container = new MiniContainer();
$container->set('greetingService', fn() => fn($name) => "Hello, $name!");
$router = new MiniRouter($container);
$router->get('/hello/{name}', function ($req, $res, $args) use ($container) {
    $greeting = $container->get('greetingService');
    return $res->withBody($greeting($args['name']));
});
```

### Service Variants with MiniContainer

You can register and use services in the container in various ways:

**Variant 1: Anonymous Function (Closure)**
```php
$container->set('greetingService', fn($c) => fn($name) => "Hello, $name!");
$greet = $container->get('greetingService');
echo $greet('Max'); // Hello, Max!
```

**Variant 2: Without Container Parameter**
```php
$container->set('simpleGreeting', fn() => fn($name) => "Hi, $name!");
$greet = $container->get('simpleGreeting');
echo $greet('Anna'); // Hi, Anna!
```

**Variant 3: Using a Class**
```php
class GreetingService {
    public function greet($name) {
        return "Hello, $name!";
    }
}
$container->set('greetingService', fn($c) => new GreetingService());
$service = $container->get('greetingService');
echo $service->greet('Tom'); // Hello, Tom!
```

**Variant 4: Using an Array as a Service**
```php
$container->set('config', fn() => [
    'greeting' => 'Hello',
    'farewell' => 'Goodbye'
]);
$config = $container->get('config');
echo $config['greeting'] . ', Max!'; // Hello, Max!
```

**Variant 5: Explicit Service Factory**
```php
$container->set('greetingService', function($c) {
    return function($name) {
        return "Hello, $name!";
    };
});
$greet = $container->get('greetingService');
echo $greet('Lisa'); // Hello, Lisa!
```



## Routing & Placeholders

- **Simple route:**  
  `$router->get('/hello/{name}', $handler);`

- **Complex pattern:**  
  `$router->get('/user/{id:\d+}', $handler); // Numbers only`

- **Named route:**  
  `$router->get('/foo', $handler)->setName('foo_route');`

- **Reverse routing:**  
  `$url = $router->urlFor('foo_route');`

- **ANY route (multi-method route):**  
  ```php
  // Route that responds to multiple methods:
  $router->any(['GET', 'POST', 'put'], '/any-demo', function ($req, $res) {
      return $res->withBody(['method' => $req->method]);
  });
  // Methods can be written in any case!
  ```

### How do placeholders and complex patterns work?

- **Simple placeholders:**  
  Everything in `{}` is recognized as a variable and passed as a value in `$args` to the handler.  
  Example: `/hello/{name}` matches `/hello/World` → `$args['name'] === 'World'`

- **Complex patterns with regex:**  
  After the name, a colon and a regular expression can follow, e.g., `{id:\d+}` for numbers only.  
  Example: `/user/{id:\d+}` matches `/user/42`, but not `/user/abc`.

- **Multiple placeholders:**  
  You can use as many placeholders as you like, e.g., `/blog/{year:\d{4}}/{slug}`.

- **Default behavior:**  
  Without regex, a placeholder accepts everything except `/` (`[^/]+`).

- **Internal:**  
  The router automatically converts the pattern into a regular expression and extracts the values as `$args`.

**Examples:**
```php
$router->get('/product/{id:\d+}', function ($req, $res, $args) {
    // $args['id'] is guaranteed to be a number
});

$router->get('/blog/{year:\d{4}}/{slug}', function ($req, $res, $args) {
    // $args['year'] = "2023", $args['slug'] = "my-article"
});

$router->get('/foo/{bar}', function ($req, $res, $args) {
    // $args['bar'] accepts anything except slash
});

// ANY route example:
$router->any(['get', 'POST'], '/any', function ($req, $res) {
    return $res->withBody(['method' => $req->method]);
});
// Methods can be written in any case!
```

---

## Notes
- **Case-insensitivity:**  
  When registering HTTP methods (e.g., with `any`), case does not matter. You can mix `'get'`, `'GET'`, `'Post'`, etc.
- **Quality:**  
  The project is checked with PHPUnit tests and PHPStan.



## Groups & API

```php
$router->group('/api', function ($group) {
    $group->addMiddleware(new ApiKeyMiddleware());
    $group->get('/user/{id:\d+}', $handler);
    $group->post('/user', $handler);
    // ...
});
```

---

## Request & Response

- **Request:**  
  - `$req->method`, `$req->uri`, `$req->query`, `$req->body`, `$req->headers`
  - `$req->getHeader('X-API-Key')`
  - `$req->withAttribute('key', $value)`, `$req->getAttribute('key')`
- **Response:**  
  - `$res->withStatus(201)`
  - `$res->withHeader('Content-Type', 'application/json')`
  - `$res->withBody(['foo' => 'bar'])`
  - `$res->send()`

---

## Reverse Routing

```php
$router->get('/hello/{name}', $handler)->setName('hello_route');
$url = $router->urlFor('hello_route', ['name' => 'World']); // /hello/World
```

---

## Error Handling

```php
try {
    $router->dispatch();
} catch (Throwable $e) {
    MiniUtils::errorResponse($e, true)->send(); // true = debug mode
}
```

---

## Complete Demo

A complete, practical example can be found in `example/demo.php`.
You can try the demo directly like this:

```bash
php -S localhost:8080 ./example/demo.php
```

Then open in your browser, e.g., [http://localhost:8080](http://localhost:8080)

The demo shows:
- Container setup
- Middleware (global, route, group, class)
- Named routes & reverse routing
- API group with auth middleware
- Various response types (text, JSON, HTML)
- Forwarding & error handling

---

## API Overview

### MiniRouter
- `addRoute($method, $pattern, $handler): MiniRoute`
- `get($pattern, $handler): MiniRoute`
- `post($pattern, $handler): MiniRoute`
- `put($pattern, $handler): MiniRoute`
- `patch($pattern, $handler): MiniRoute`
- `delete($pattern, $handler): MiniRoute`
- `options($pattern, $handler): MiniRoute`
- `any(array $methods, string $pattern, $handler): array`  <!-- Corrected signature -->
- `group($prefix, $callback): MiniRouteGroup`
- `addMiddleware($middleware): self`
- `urlFor($name, $params = []): ?string`
- `dispatch(): void`
- `forward($path, $method = 'GET', $req = null, $res = null)`
- `getCurrentRoute(): ?MiniRoute`
- `public array $routes`

### MiniRoute
- `setName($name): self`
- `getName(): ?string`
- `addMiddleware($middleware): self`
- `public string $pattern`
- `public string $method`
- `public array $middleware`
- `public ?MiniRouteGroup $group`
- `public $handler`
- `public ?string $name`

### MiniRouteGroup
- `addMiddleware($middleware): self`
- `addRoute(MiniRoute $route): self`
- `getRoutes(): array`
- `getMiddleware(): array`
- `public string $prefix`
- `public array $middleware`
- `public array $routes`

### MiniRequest
- `public string $method`
- `public string $uri`
- `public array $query`
- `public mixed $body`
- `public array $headers`
- `public array $attributes`
- `getHeader($name): mixed`
- `withAttribute($key, $value): self`
- `getAttribute($key): mixed`

### MiniResponse
- `withStatus($status): self`
- `withHeader($name, $value): self`
- `withBody($body): self`
- `send(): void`

### MiniContainer
- `set($name, $factory): void`
- `get($name): mixed`
- `has($name): bool`

---

## License & Author

**License:** MIT  
**Author:** myCodebox

---
