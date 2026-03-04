<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use MyCodebox\MiniRouter\Core\MiniRouter;
use MyCodebox\MiniRouter\Core\MiniRequest;
use MyCodebox\MiniRouter\Core\MiniResponse;

/**
 * Test subclass that exposes handleRoute as public.
 */
class TestableMiniRouter extends MiniRouter
{
    public function publicHandleRoute(
        string $method,
        string $uri,
        MiniRequest $request,
        MiniResponse $response,
    ): MiniResponse {
        return $this->handleRoute($method, $uri, $request, $response);
    }
}
class MiniRouterTest extends TestCase
{
    /**
     * Tests if a simple GET route works correctly.
     */
    public function testSimpleGetRoute()
    {
        $router = new TestableMiniRouter();
        $router->get('/test/{name}', function (
            MiniRequest $req,
            MiniResponse $res,
            array $args = [],
        ) {
            return $res->withBody('Hello, ' . $args['name']);
        });
        $request  = new MiniRequest('GET', '/test/Welt');
        $response = new MiniResponse();
        $result   = $router->publicHandleRoute(
            'GET',
            '/test/Welt',
            $request,
            $response,
        );
        $this->assertInstanceOf(MiniResponse::class, $result);
        $this->assertEquals('Hello, Welt', $result->body);
        $this->assertEquals(200, $result->status);
    }

    /**
     * Tests case insensitivity when registering ANY routes.
     */
    public function testAnyRouteCaseInsensitivity()
    {
        $router = new TestableMiniRouter();
        $router->any(
            ['get', 'PoSt', 'Put', 'patch', 'DELETE', 'options', 'HEAD'],
            '/case-any',
            function (MiniRequest $req, MiniResponse $res) {
                return $res->withBody('ANY-' . $req->method);
            },
        );

        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

        foreach ($methods as $method) {
            $request  = new MiniRequest($method, '/case-any');
            $response = new MiniResponse();
            $result   = $router->publicHandleRoute(
                $method,
                '/case-any',
                $request,
                $response,
            );
            $this->assertEquals(
                'ANY-' . $method,
                $result->body,
                "Error with method $method",
            );
        }
    }

    /**
     * Tests ANY route for all HTTP methods.
     */
    public function testAnyRouteAllMethods()
    {
        $router = new TestableMiniRouter();
        $router->any(
            ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'],
            '/any-all',
            function (MiniRequest $req, MiniResponse $res) {
                return $res->withBody('ALL-' . $req->method);
            },
        );

        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

        foreach ($methods as $method) {
            $request  = new MiniRequest($method, '/any-all');
            $response = new MiniResponse();
            $result   = $router->publicHandleRoute(
                $method,
                '/any-all',
                $request,
                $response,
            );
            $this->assertEquals(
                'ALL-' . $method,
                $result->body,
                "Error with method $method",
            );
        }
    }

    /**
     * Tests that invalid HTTP methods are ignored.
     */
    public function testInvalidHttpMethodIsIgnored()
    {
        $router = new TestableMiniRouter();
        $router->any(['GET', 'FOOBAR', 'POST'], '/invalid-method', function (
            MiniRequest $req,
            MiniResponse $res,
        ) {
            return $res->withBody('OK');
        });

        // FOOBAR should be ignored, GET and POST should work
        $request  = new MiniRequest('GET', '/invalid-method');
        $response = new MiniResponse();
        $result   = $router->publicHandleRoute(
            'GET',
            '/invalid-method',
            $request,
            $response,
        );
        $this->assertEquals('OK', $result->body);

        $request  = new MiniRequest('POST', '/invalid-method');
        $response = new MiniResponse();
        $result   = $router->publicHandleRoute(
            'POST',
            '/invalid-method',
            $request,
            $response,
        );
        $this->assertEquals('OK', $result->body);

        // FOOBAR should return 404
        $request  = new MiniRequest('FOOBAR', '/invalid-method');
        $response = new MiniResponse();
        $result   = $router->publicHandleRoute(
            'FOOBAR',
            '/invalid-method',
            $request,
            $response,
        );
        $this->assertEquals(404, $result->status);
    }

    /**
     * Tests fallback/404 for non-existent routes.
     */
    public function testFallbackRoute404()
    {
        $router = new TestableMiniRouter();
        $router->get('/exists', function (MiniRequest $req, MiniResponse $res) {
            return $res->withBody('OK');
        });

        $request  = new MiniRequest('GET', '/not-found');
        $response = new MiniResponse();
        $result   = $router->publicHandleRoute(
            'GET',
            '/not-found',
            $request,
            $response,
        );
        $this->assertEquals(404, $result->status);
    }

    /**
     * Tests body handling for ANY route with POST/PUT/PATCH.
     */
    public function testAnyRouteBodyHandling()
    {
        $router = new TestableMiniRouter();
        $router->any(['POST', 'PUT', 'PATCH'], '/any-body', function (
            MiniRequest $req,
            MiniResponse $res,
        ) {
            return $res->withBody([
                'method' => $req->method,
                'body'   => $req->body,
            ]);
        });

        $bodies = [
            'POST'  => ['foo' => 'bar'],
            'PUT'   => ['baz' => 123],
            'PATCH' => ['x' => true],
        ];

        foreach ($bodies as $method => $body) {
            $request  = new MiniRequest($method, '/any-body', [], $body);
            $response = new MiniResponse();
            $result   = $router->publicHandleRoute(
                $method,
                '/any-body',
                $request,
                $response,
            );
            $this->assertEquals($method, $result->body['method']);
            $this->assertEquals($body, $result->body['body']);
        }
    }
    /**
     * Tests if middleware can block a request (e.g., Unauthorized).
     */
    public function testMiddlewareBlocksUnauthorized()
    {
        $router = new TestableMiniRouter();
        $router->addMiddleware(function (
            MiniRequest $req,
            MiniResponse $res,
            $next,
        ) {
            return $res->withStatus(401)->withBody('Unauthorized');
        });
        $router->get('/test', function (MiniRequest $req, MiniResponse $res) {
            return $res->withBody('OK');
        });
        $request  = new MiniRequest('GET', '/test');
        $response = new MiniResponse();
        $result   = $router->publicHandleRoute(
            'GET',
            '/test',
            $request,
            $response,
        );
        $this->assertEquals(401, $result->status);
        $this->assertEquals('Unauthorized', $result->body);
    }
    /**
     * Tests if a pattern constraint (e.g., digits only) works correctly.
     */
    public function testRoutePatternConstraint()
    {
        $router = new TestableMiniRouter();
        $router->get("/user/{id:\d+}", function (
            MiniRequest $req,
            MiniResponse $res,
            array $args = [],
        ) {
            return $res->withBody('User: ' . $args['id']);
        });
        $request  = new MiniRequest('GET', '/user/42');
        $response = new MiniResponse();
        $result   = $router->publicHandleRoute(
            'GET',
            '/user/42',
            $request,
            $response,
        );
        $this->assertEquals(200, $result->status);
        $this->assertEquals('User: 42', $result->body);
        $requestInvalid  = new MiniRequest('GET', '/user/abc');
        $responseInvalid = new MiniResponse();
        $resultInvalid   = $router->publicHandleRoute(
            'GET',
            '/user/abc',
            $requestInvalid,
            $responseInvalid,
        );
        $this->assertEquals(404, $resultInvalid->status);
    }
    /**
     * Tests reverse routing (urlFor) for named routes.
     */
    public function testReverseRoutingUrlFor()
    {
        $router = new TestableMiniRouter();
        $router
            ->get('/article/{slug}', function (
                MiniRequest $req,
                MiniResponse $res,
                array $args = [],
            ) {
                return $res->withBody('Artikel: ' . $args['slug']);
            })
            ->setName('article_detail');
        $url = $router->urlFor('article_detail', ['slug' => 'mein-artikel']);
        $this->assertEquals('/article/mein-artikel', $url);
        $urlMissing = $router->urlFor('article_detail');
        $this->assertEquals('/article/{slug}', $urlMissing);
        $urlNotFound = $router->urlFor('not_found');
        $this->assertNull($urlNotFound);
    }

    /**
     * Tests route groups with prefix and middleware.
     */
    public function testRouteGroupWithPrefixAndMiddleware()
    {
        $router = new TestableMiniRouter();
        $router->group('/api', function ($group) {
            $group->addMiddleware(function (
                MiniRequest $req,
                MiniResponse $res,
                $next,
            ) {
                $res = $res->withHeader('X-Group', 'yes');

                return $next($req, $res);
            });
            $group->get('/foo', function (MiniRequest $req, MiniResponse $res) {
                return $res->withBody('Gruppenroute');
            });
        });
        $request  = new MiniRequest('GET', '/api/foo');
        $response = new MiniResponse();
        $result   = $router->publicHandleRoute(
            'GET',
            '/api/foo',
            $request,
            $response,
        );
        $this->assertEquals('Gruppenroute', $result->body);
        $this->assertArrayHasKey('X-Group', $result->headers);
        $this->assertEquals('yes', $result->headers['X-Group']);
    }
    /**
     * Tests various HTTP methods (POST, PUT, PATCH, DELETE, OPTIONS, ANY).
     */
    public function testVariousHttpMethods()
    {
        $router = new TestableMiniRouter();
        $router->post('/post', fn ($req, $res) => $res->withBody('POST'));
        $router->put('/put', fn ($req, $res) => $res->withBody('PUT'));
        $router->patch('/patch', fn ($req, $res) => $res->withBody('PATCH'));
        $router->delete('/delete', fn ($req, $res) => $res->withBody('DELETE'));
        $router->options(
            '/options',
            fn ($req, $res) => $res->withBody('OPTIONS'),
        );
        $router->any('get', '/any', fn ($req, $res) => $res->withBody('ANY'));
        $router->any('post', '/any', fn ($req, $res) => $res->withBody('ANY'));
        $this->assertEquals(
            'POST',
            $router->publicHandleRoute(
                'POST',
                '/post',
                new MiniRequest('POST', '/post'),
                new MiniResponse(),
            )->body,
        );
        $this->assertEquals(
            'PUT',
            $router->publicHandleRoute(
                'PUT',
                '/put',
                new MiniRequest('PUT', '/put'),
                new MiniResponse(),
            )->body,
        );
        $this->assertEquals(
            'PATCH',
            $router->publicHandleRoute(
                'PATCH',
                '/patch',
                new MiniRequest('PATCH', '/patch'),
                new MiniResponse(),
            )->body,
        );
        $this->assertEquals(
            'DELETE',
            $router->publicHandleRoute(
                'DELETE',
                '/delete',
                new MiniRequest('DELETE', '/delete'),
                new MiniResponse(),
            )->body,
        );
        $this->assertEquals(
            'OPTIONS',
            $router->publicHandleRoute(
                'OPTIONS',
                '/options',
                new MiniRequest('OPTIONS', '/options'),
                new MiniResponse(),
            )->body,
        );
        $this->assertEquals(
            'ANY',
            $router->publicHandleRoute(
                'GET',
                '/any',
                new MiniRequest('GET', '/any'),
                new MiniResponse(),
            )->body,
        );
        $this->assertEquals(
            'ANY',
            $router->publicHandleRoute(
                'POST',
                '/any',
                new MiniRequest('POST', '/any'),
                new MiniResponse(),
            )->body,
        );
    }
    /**
     * Tests error cases: invalid handler and exception in handler.
     */
    public function testErrorCases()
    {
        $router = new TestableMiniRouter();
        $router->get('/invalid', 'not_callable_handler');
        $request  = new MiniRequest('GET', '/invalid');
        $response = new MiniResponse();
        $result   = $router->publicHandleRoute(
            'GET',
            '/invalid',
            $request,
            $response,
        );
        $this->assertEquals(500, $result->status);
        $this->assertStringContainsString(
            'not_callable_handler',
            is_array($result->body)
                ? $result->body['error'] ?? ''
                : (string) $result->body,
        );
        $router = new TestableMiniRouter();
        $router->get('/fail', function () {
            throw new \RuntimeException('Absichtlicher Fehler');
        });
        $request  = new MiniRequest('GET', '/fail');
        $response = new MiniResponse();
        $result   = $router->publicHandleRoute(
            'GET',
            '/fail',
            $request,
            $response,
        );
        $this->assertEquals(500, $result->status);
        $this->assertStringContainsString(
            'Absichtlicher Fehler',
            is_array($result->body)
                ? $result->body['error'] ?? ''
                : (string) $result->body,
        );
    }
    /**
     * Tests container: service, factory, and error case.
     */
    public function testContainerServiceAndFactory()
    {
        $container = new \MyCodebox\MiniRouter\Core\MiniContainer();
        $container->set('foo', 'bar');
        $container->set('baz', function ($c) {
            return 'baz-' . $c->get('foo');
        });
        $this->assertTrue($container->has('foo'));
        $this->assertEquals('bar', $container->get('foo'));
        $this->assertEquals('baz-bar', $container->get('baz'));
        $this->assertFalse($container->has('notfound'));
        $this->expectException(
            \MyCodebox\MiniRouter\Exceptions\MiniContainerException::class,
        );
        $container->get('notfound');
    }
    /**
     * Tests MiniResponse: status, header, and body.
     */
    public function testMiniResponseMethods()
    {
        $res  = new MiniResponse();
        $res2 = $res
            ->withStatus(201)
            ->withHeader('X-Test', 'abc')
            ->withBody('Hallo');
        $this->assertEquals(201, $res2->status);
        $this->assertArrayHasKey('X-Test', $res2->headers);
        $this->assertEquals('abc', $res2->headers['X-Test']);
        $this->assertEquals('Hallo', $res2->body);
    }
    /**
     * Tests MiniRequest: attributes, header, and body.
     */
    public function testMiniRequestAttributesHeadersBody()
    {
        $req  = new MiniRequest('GET', '/foo', [], null, ['X-Test' => 'abc']);
        $req2 = $req->withAttribute('bar', 123);
        $this->assertNull($req->getAttribute('bar'));
        $this->assertEquals(123, $req2->getAttribute('bar'));
        $this->assertEquals('abc', $req->getHeader('X-Test'));
    }
    /**
     * Tests reverse routing with multiple placeholders.
     */
    public function testReverseRoutingMultiplePlaceholders()
    {
        $router = new TestableMiniRouter();
        $router
            ->get('/user/{id}/post/{slug}', function () {
            })
            ->setName('user_post');
        $url = $router->urlFor('user_post', [
            'id'   => 42,
            'slug' => 'mein-post',
        ]);
        $this->assertEquals('/user/42/post/mein-post', $url);
    }

    /**
     * Tests if multiple middleware are executed in the correct order.
     */

    public function testMultipleMiddlewareExecutionOrder()
    {
        $router = new TestableMiniRouter();

        $router->addMiddleware(function (
            MiniRequest $req,
            MiniResponse $res,
            $next,
        ) {
            $res = $res->withHeader('X-First', '1');

            return $next($req, $res);
        });

        $router->addMiddleware(function (
            MiniRequest $req,
            MiniResponse $res,
            $next,
        ) {
            $response = $next($req, $res);

            return $response->withBody($response->body . ' + MW2');
        });

        $router->get('/mwtest', function (MiniRequest $req, MiniResponse $res) {
            return $res->withBody('Handler');
        });

        $request = new MiniRequest('GET', '/mwtest');

        $response = new MiniResponse();

        $result = $router->publicHandleRoute(
            'GET',
            '/mwtest',
            $request,
            $response,
        );

        $this->assertEquals('Handler + MW2', $result->body);

        $this->assertArrayHasKey('X-First', $result->headers);

        $this->assertEquals('1', $result->headers['X-First']);
    }

    public function testRouteWithMultiplePlaceholdersAndMiddleware()
    {
        $router = new TestableMiniRouter();
        $router->addMiddleware(function ($req, $res, $next) {
            $res = $res->withHeader('X-Test', 'ok');

            return $next($req, $res);
        });
        $router->get('/foo/{bar}/baz/{id}', function ($req, $res, $args = []) {
            return $res->withBody($args['bar'] . '-' . $args['id']);
        });
        $request  = new MiniRequest('GET', '/foo/abc/baz/123');
        $response = new MiniResponse();
        $result   = $router->publicHandleRoute(
            'GET',
            '/foo/abc/baz/123',
            $request,
            $response,
        );
        $this->assertEquals('abc-123', $result->body);
        $this->assertArrayHasKey('X-Test', $result->headers);
        $this->assertEquals('ok', $result->headers['X-Test']);
    }
}
