<?php

declare(strict_types=1);

namespace MyCodebox\MiniRouter\Core;

class MiniResponse
{
    public int $status;
    /** @var array<string, string|array<int, string>> */
    public array $headers = [];
    public mixed $body;

    /**
     * Constructs a new MiniResponse instance.
     *
     * @param array<string, string|array<int, string>> $headers
     */
    public function __construct(
        int $status = 200,
        array $headers = [],
        mixed $body = null,
    ) {
        $this->status  = $status;
        $this->headers = $headers;
        $this->body    = $body;
    }

    /**
     * Normalizes a header name to proper case (e.g., content-type => Content-Type).
     */
    private static function normalizeHeaderName(string $name): string
    {
        $parts = explode('-', $name);
        $parts = array_map(fn ($p) => ucfirst(strtolower($p)), $parts);

        return implode('-', $parts);
    }

    /**
     * Returns a new instance with the given status, headers, and/or body.
     *
     * @param array<string, string|array<int, string>>|null $headers
     */
    public function with(
        ?int $status = null,
        ?array $headers = null,
        mixed $body = null,
    ): self {
        $clonedResponse = clone $this;

        if ($status !== null) {
            $clonedResponse->status = $status;
        }

        if ($headers !== null) {
            $clonedResponse->headers = $headers;
        }

        if ($body !== null) {
            $clonedResponse->body = $body;
        }

        return $clonedResponse;
    }

    /**
     * Returns a new instance with the given status code.
     */
    public function withStatus(int $status): self
    {
        $clonedResponse         = clone $this;
        $clonedResponse->status = $status;

        return $clonedResponse;
    }

    /**
     * Returns a new instance with the given header set.
     *
     * @param string|array<int, string> $value
     */
    public function withHeader(string $name, string|array $value): self
    {
        $clonedResponse                                 = clone $this;
        $normalizedHeaderName                           = self::normalizeHeaderName($name);
        $clonedResponse->headers[$normalizedHeaderName] = $value;

        return $clonedResponse;
    }

    /**
     * Returns a new instance with the given body.
     */
    public function withBody(mixed $body): self
    {
        $clonedResponse       = clone $this;
        $clonedResponse->body = $body;

        return $clonedResponse;
    }

    /**
     * Sends the response to the client (status, headers, and body).
     */
    public function send(): void
    {
        // Status
        http_response_code($this->status);

        // Headers
        foreach ($this->headers as $headerName => $headerValue) {
            if (is_array($headerValue)) {
                foreach ($headerValue as $v) {
                    header(
                        self::normalizeHeaderName($headerName) . ': ' . $v,
                        false,
                    );
                }
            } else {
                header(
                    self::normalizeHeaderName($headerName) .
                        ': ' .
                        $headerValue,
                );
            }
        }

        // Body
        if (is_array($this->body) || is_object($this->body)) {
            // JSON response
            if (!isset($this->headers['Content-Type'])) {
                header('Content-Type: application/json');
            }
            echo json_encode(
                $this->body,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } else {
            echo $this->body;
        }
    }
}
