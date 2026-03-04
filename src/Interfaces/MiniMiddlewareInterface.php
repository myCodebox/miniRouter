<?php

declare(strict_types=1);

namespace MyCodebox\MiniRouter\Interfaces;

use MyCodebox\MiniRouter\Core\MiniRequest;
use MyCodebox\MiniRouter\Core\MiniResponse;

interface MiniMiddlewareInterface
{
    public function process(
        MiniRequest $req,
        MiniResponse $res,
        callable $next,
    ): mixed;
}
