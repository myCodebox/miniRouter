<?php

declare(strict_types=1);

namespace MyCodebox\MiniRouter\Core;

use MyCodebox\MiniRouter\Exceptions\MiniContainerException;

class MiniContainer
{
    /** @var array<string, mixed> */
    private array $services = [];
    /** @var array<string, callable> */
    private array $factories = [];

    /**
     * Registers a service or factory in the container.
     */
    public function set(string $name, mixed $serviceOrFactory): void
    {
        if (is_callable($serviceOrFactory)) {
            $this->factories[$name] = $serviceOrFactory;
        } else {
            $this->services[$name] = $serviceOrFactory;
        }
    }

    /**
     * Returns a service, creating it from a factory if necessary.
     *
     * @throws MiniContainerException
     */
    public function get(string $name): mixed
    {
        if (array_key_exists($name, $this->services)) {
            return $this->services[$name];
        }

        if (array_key_exists($name, $this->factories)) {
            $factory = $this->factories[$name];

            $service = $factory($this);

            $this->services[$name] = $service;

            return $service;
        }

        throw new MiniContainerException(
            "Service or factory '$name' not found in container.",
        );
    }

    /**
     * Checks if a service or factory exists.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->services) || array_key_exists($name, $this->factories);
    }
}
