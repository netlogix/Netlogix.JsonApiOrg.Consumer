<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Netlogix\JsonApiOrg\Consumer\Guzzle\Middleware\EndpointCacheMiddleware;

final class DefaultClientProvider implements ClientProvider
{

    public function createClient(): Client
    {
        $stack = HandlerStack::create();
        $stack->push(new EndpointCacheMiddleware(), 'endpoint-cache');
        return new Client(['handler' => $stack]);
    }

}
