<?php

declare(strict_types=1);

namespace MyCodebox\MiniRouter\Core;

class MiniRequest
{
    public string $method;
    public string $uri;
    /** @var array<string, mixed> */
    public array $query;
    public mixed $body;
    /** @var array<string, string|array<string>|null> */
    public array $headers;
    /** @var array<string, mixed> */
    public array $attributes;

    /**
     * @param array<string, mixed> $query
     * @param array<string, string|array<string>|null> $headers
     * @param array<string, mixed> $attributes
     *
     * Constructs a new MiniRequest instance.
     */
    public function __construct(
        string $method,
        string $uri,
        array $query = [],
        mixed $body = null,
        array $headers = [],
        array $attributes = [],
    ) {
        $this->method     = strtoupper($method);
        $this->uri        = $uri;
        $this->query      = $query;
        $this->body       = $body;
        $this->headers    = $headers;
        $this->attributes = $attributes;
    }

    /**
     * Fallback for getting all headers if not provided.
     *
     * @return array<string, string>
     */
    public static function getHeadersFallback(): array
    {
        $headers = [];

        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $header = str_replace(
                    ' ',
                    '-',
                    ucwords(
                        strtolower(str_replace('_', ' ', substr($name, 5))),
                    ),
                );
                $headers[$header] = $value;
            } elseif (
                in_array($name, [
                    'CONTENT_TYPE',
                    'CONTENT_LENGTH',
                    'CONTENT_MD5',
                ])
            ) {
                $header = str_replace(
                    ' ',
                    '-',
                    ucwords(strtolower(str_replace('_', ' ', $name))),
                );
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Parses the request body based on content type.
     */
    public static function parseBody(
        ?string $rawInput = null,
        ?string $contentType = null,
    ): mixed {
        $rawInput    = $rawInput    ?? file_get_contents('php://input') ?: '';
        $contentType = $contentType ?? ($_SERVER['CONTENT_TYPE'] ?? '');

        if (stripos($contentType, 'application/json') === 0) {
            $decodedJson = json_decode((string) $rawInput, true);

            return $decodedJson ?? $rawInput;
        } elseif (
            stripos($contentType, 'application/x-www-form-urlencoded') === 0
        ) {
            parse_str((string) $rawInput, $parsed);

            return $parsed;
        } elseif (stripos($contentType, 'multipart/form-data') === 0) {
            return $_POST;
        }

        return $rawInput;
    }

    /**
     * Returns the value of a header (case-insensitive), or null if not found.
     */
    public function getHeader(string $name): mixed
    {
        $lowerName = strtolower($name);

        foreach ($this->headers as $headerKey => $headerValue) {
            if (strtolower($headerKey) === $lowerName) {
                if (is_array($headerValue)) {
                    return $headerValue[0] ?? null;
                }

                return $headerValue;
            }
        }

        return null;
    }

    /**
     * Returns a new instance with an added/replaced attribute.
     */
    public function withAttribute(string $name, mixed $value): self
    {
        $clone                    = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    /**
     * Returns the value of an attribute, or null if not set.
     */
    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }
}
