<?php

declare(strict_types=1);

namespace MyCodebox\MiniRouter\Core;

use InvalidArgumentException;
use Throwable;
use MyCodebox\MiniRouter\Interfaces\MiniMiddlewareInterface;

class MiniUtils
{
    /** Real HTTP methods (for routing only) */
    public const HTTP_METHODS = [
        'get',
        'post',
        'put',
        'patch',
        'delete',
        'options',
        'head',
    ];
    /**
     * Validates that the given middleware is either callable or implements MiniMiddlewareInterface.
     *
     * @throws InvalidArgumentException
     */
    public static function assertValidMiddleware(mixed $middleware): void
    {
        if (
            !is_callable($middleware) && !($middleware instanceof MiniMiddlewareInterface)
        ) {
            throw new InvalidArgumentException(
                'Middleware must be callable or implement MiniMiddlewareInterface.',
            );
        }
    }

    /**
     * Converts a route pattern with placeholders to a regular expression.
     */
    public static function convertPattern(string $pattern): string
    {
        // Replace placeholders with regex, escape only outside placeholders
        $regex = preg_replace_callback(
            "/\{(\w+)(?::([^}]+))?\}/",
            function ($matches) {
                $name       = $matches[1];
                $subPattern = isset($matches[2]) ? $matches[2] : '[^/]+';

                return '(?P<' . $name . '>' . $subPattern . ')';
            },
            $pattern,
        );

        // Escape all other regex special characters, but keep () and ?P<> for named groups
        $regex = preg_replace_callback(
            "/((?:\(\?P<[^>]+>[^)]+\))|[^()]+)/",
            function ($m) {
                if (str_starts_with($m[0], '(?P<')) {
                    return $m[0];
                }

                return preg_quote($m[0], '/');
            },
            (string) $regex,
        );

        // Optional trailing slash
        return (string) $regex . '/?';
    }

    /**
     * Extracts named arguments from a regex match array.
     *
     * @param array<string|int, mixed> $matches
     *
     * @return array<string, mixed>
     */
    public static function extractArgs(array $matches): array
    {
        /** @var array<string, mixed> $args */
        $args = [];

        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $args[$key] = $value;
            }
        }

        return $args;
    }

    /**
     * Sends an error response for exceptions.
     */
    public static function errorResponse(
        Throwable $e,
        bool $debug = false,
    ): MiniResponse {
        $body = [
            'error' => $e->getMessage(),
        ];

        if ($debug) {
            $body['exception'] = get_class($e);
            $body['file']      = $e->getFile();
            $body['line']      = $e->getLine();
            $body['trace']     = $e->getTraceAsString();
        }

        return new MiniResponse()
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }
}
