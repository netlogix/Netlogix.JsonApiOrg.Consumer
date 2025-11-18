<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use Netlogix\JsonApiOrg\Consumer\Guzzle\Middleware\EndpointCacheMiddleware;

final class CurlMultiHandlerClientProvider implements ClientProvider
{
    public ?Client $client = null;

    protected ?CurlMultiHandler $handler;

    public function createClient(): Client
    {
        if ($this->client === null) {
            $stack = HandlerStack::create($this->getHandler());
            $stack->push(
                middleware: EndpointCacheMiddleware::create()
                    ->withHttpMethods('GET')
                    ->withHeaderNames('Host', 'User-Agent'),
                name: 'endpoint-cache'
            );
            $this->client = new Client(['handler' => $stack]);
        }
        return $this->client;
    }

    public function getHandler(): CurlMultiHandler
    {
        return $this->handler = $this->handler ?? new CurlMultiHandler();
    }

}
