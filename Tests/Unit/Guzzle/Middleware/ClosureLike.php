<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Tests\Unit\Guzzle\Middleware;

use Psr\Http\Message\RequestInterface;

interface ClosureLike
{
    public function __invoke(RequestInterface $request, array $options);
}