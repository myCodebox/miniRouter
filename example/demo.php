<?php

require_once __DIR__ . '/../vendor/autoload.php';

use MyCodebox\MiniRouter\Interfaces\MiniMiddlewareInterface;
use MyCodebox\MiniRouter\Core\MiniRequest;
use MyCodebox\MiniRouter\Core\MiniResponse;
use MyCodebox\MiniRouter\Core\MiniContainer;
use MyCodebox\MiniRouter\Core\MiniRouter;
use MyCodebox\MiniRouter\Core\MiniUtils;

class ApiKeyMiddleware implements MiniMiddlewareInterface
{
    // Typed middleware signature as requested
    public function process(
        MiniRequest $req,
        MiniResponse $res,
        callable $next,
    ): mixed {
        if (($req->getHeader('X-API-Key') ?? '') !== '12345') {
            return $res
                ->withStatus(401)
                // Letting MiniResponse handle JSON encoding for arrays; keep header optional
                ->withBody([
                    'error' => 'Unauthorized',
                    'hint'  => 'Set X-API-Key header to 12345',
                ]);
        }

        return $next($req, $res);
    }
}

//
// === 1. Container & Router Setup ===
//
$container = new MiniContainer();
$container->set('greetingService', fn ($c) => fn ($name) => "Hello, $name!");

// Enable debug mode for router (second constructor argument)
$router = new MiniRouter($container, true);

//
// === 2. Global Middleware ===
//
$router->addMiddleware(function (
    MiniRequest $req,
    MiniResponse $res,
    callable $next,
) {
    error_log('Request: ' . $req->method . ' ' . $req->uri);
    $req = $req->withAttribute('authorized', true);

    return $next($req, $res);
});

//
// === 3. Single route with name, middleware, and reverse routing ===
//
$router
    ->get('/hello/{name}', function (
        MiniRequest $req,
        MiniResponse $res,
        array $args = [],
    ) {
        global $container;
        $greet = $container->get('greetingService');

        return $res
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($greet($args['name']));
    })
    ->setName('hello_route')
    ->addMiddleware(function (
        MiniRequest $req,
        MiniResponse $res,
        callable $next,
    ) {
        if ($req->getAttribute('authorized') !== true) {
            return $res
                ->withStatus(403)
                ->withHeader('Content-Type', 'text/plain')
                ->withBody('Forbidden');
        }

        return $next($req, $res);
    });

// Example for reverse routing (URL generation)
$url = $router->urlFor('hello_route', ['name' => 'Reverse']);
error_log('Reverse routing example: ' . $url); // Outputs /hello/Reverse

$router->group('/api', function ($group) {
    // Example: Middleware as class (typed)
    $group->addMiddleware(new ApiKeyMiddleware());

    // Complex pattern: id must be a number
    $group->get("/user/{id:\d+}", function (
        MiniRequest $req,
        MiniResponse $res,
        array $args = [],
    ) {
        $user = ['id' => $args['id'], 'name' => 'User ' . $args['id']];

        // Return array — MiniResponse::send() automatically sets Content-Type: application/json
        return $res->withBody($user);
    });

    $group->post('/user', function (MiniRequest $req, MiniResponse $res) {
        return $res->withBody([
            'status' => 'created',
            'data'   => $req->body,
        ]);
    });

    // PUT example: Update user
    $group->put("/user/{id:\d+}", function (
        MiniRequest $req,
        MiniResponse $res,
        array $args = [],
    ) {
        return $res->withBody([
            'status' => 'updated',
            'id'     => $args['id'],
            'data'   => $req->body,
        ]);
    });

    // PATCH example: Partial update user
    $group->patch("/user/{id:\d+}", function (
        MiniRequest $req,
        MiniResponse $res,
        array $args = [],
    ) {
        return $res->withBody([
            'status' => 'patched',
            'id'     => $args['id'],
            'data'   => $req->body,
        ]);
    });

    // DELETE example: Delete user
    $group->delete("/user/{id:\d+}", function (
        MiniRequest $req,
        MiniResponse $res,
        array $args = [],
    ) {
        return $res->withBody([
            'status' => 'deleted',
            'id'     => $args['id'],
        ]);
    });
});

//
// === 5. Various response types ===
//
$router->get('/text-demo', function (MiniRequest $req, MiniResponse $res) {
    return $res
        ->withHeader('Content-Type', 'text/plain')
        ->withBody('This is a plain text response.');
});

$router->get('/json-demo', function (MiniRequest $req, MiniResponse $res) {
    $data = ['status' => 'ok', 'message' => 'This is a JSON response'];

    // Return array; no explicit JSON-encoding
    return $res->withBody($data);
});

$router->get('/html-demo', function (MiniRequest $req, MiniResponse $res) {
    return $res
        ->withHeader('Content-Type', 'text/html')
        ->withBody(
            '<h2>MiniRouter HTML Demo</h2><p>This is an HTML response.</p>',
        );
});

// === ANY route demo ===
$router->any(['get', 'post'], '/any-demo', function (
    MiniRequest $req,
    MiniResponse $res,
) {
    return $res->withBody([
        'message' => 'This is an ANY route!',
        'method'  => $req->method,
        'body'    => $req->body,
    ]);
});

//
// === 6. Fallback route (homepage) ===
//
$router->get('/', function (MiniRequest $req, MiniResponse $res) {
    global $router;

    return $res->withHeader('Content-Type', 'text/html')->withBody(
        '<h1>MiniRouter Demo</h1>
            <ul>
                <li><span>GET</span> <a href="/hello/World">/hello/{name}</a></li>
                <li><span>GET</span> <a href="/api/user/42" onclick="alert(\'For the API route /api/user/{id} you must set the header X-API-Key=12345 (e.g. with a tool like Postman or fetch).\'); return false;">/api/user/{id:\d+}</a> <small>(Header <code>X-API-Key: 12345</code> required, numbers only)</small></li>
                <li><span>POST</span> <a href="/api/user">/api/user</a> <small>(Header <code>X-API-Key: 12345</code> required, send data via POST)</small></li>
                <li><span>PUT</span> <a href="#" onclick="alert(\'PUT to /api/user/{id} (only possible via tool like Postman or fetch)\'); return false;">/api/user/{id:\d+}</a> <small>(Header <code>X-API-Key: 12345</code> required, send data via PUT)</small></li>
                <li><span>PATCH</span> <a href="#" onclick="alert(\'PATCH to /api/user/{id} (only possible via tool like Postman or fetch)\'); return false;">/api/user/{id:\d+}</a> <small>(Header <code>X-API-Key: 12345</code> required, send data via PATCH)</small></li>
                <li><span>DELETE</span> <a href="#" onclick="alert(\'DELETE to /api/user/{id} (only possible via tool like Postman or fetch)\'); return false;">/api/user/{id:\d+}</a> <small>(Header <code>X-API-Key: 12345</code> required)</small></li>
                <li><span>GET</span> <a href="/forward-demo">/forward-demo</a> <small>(Server-side forwarding to <code>/hello/Forward</code>)</small></li>
                <li><span>GET</span> <a href="/text-demo">/text-demo</a> <small>(Text response)</small></li>
                <li><span>GET</span> <a href="/json-demo">/json-demo</a> <small>(JSON response)</small></li>
                <li><span>GET</span> <a href="/html-demo">/html-demo</a> <small>(HTML response)</small></li>
                <li><span>ANY</span> <a href="/any-demo">/any-demo</a> <small>(Accepts all HTTP methods)</small></li>
                <li><span>GET</span> <a href="/current-route">/current-route</a> <small>(Shows the current route as JSON)</small></li>
            </ul>
            <p><small>For the API route: Set the header <code>X-API-Key: 12345</code></small></p>',
    );
});

$router->get('/forward-demo', function (
    MiniRequest $req,
    MiniResponse $res,
) use ($router) {
    // Forwarding to /hello/Forward
    return $router->forward('/hello/Forward', 'GET', $req, $res);
});

// === 7. Route to display the current route after dispatch ===
$router->get('/current-route', function (
    MiniRequest $req,
    MiniResponse $res,
) use ($router) {
    $currentRoute = $router->getCurrentRoute();

    if ($currentRoute) {
        $info = [
            'name'    => $currentRoute->getName(),
            'pattern' => $currentRoute->pattern,
            'method'  => $currentRoute->method,
        ];

        return $res->withBody($info);
    } else {
        return $res->withBody(['error' => 'No current route found']);
    }
});

//
// === 8. Global error handling & dispatch ===
//
try {
    $router->dispatch();
} catch (\Throwable $e) {
    // Show debug output since router is in debug mode; this is consistent with earlier behavior
    MiniUtils::errorResponse($e, true)->send(); // true = debug mode
}
