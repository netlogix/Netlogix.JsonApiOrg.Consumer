<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Tests\Common\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neos\Flow\Annotations as Flow;
use Netlogix\JsonApiOrg\Consumer\Guzzle\ClientProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * @Flow\Scope("singleton")
 */
final class TestingClientProvider implements ClientProvider
{

    /**
     * FIXME: Use Flow Cache
     *
     * @var array<string, ResponseInterface>
     */
    private $responsesForUris = [];

    /**
     * @var array
     */
    private $history = [];

    public function __construct()
    {
    }

    public function createClient(): Client
    {
        $requestHandler = function (RequestInterface $request) {
            $uri = (string)$request->getUri();
            if (array_key_exists($uri, $this->responsesForUris)) {
                return Create::promiseFor($this->responsesForUris[$uri]);
            }

            if (strpos($uri, 'resource://') === 0
                || strpos($uri, 'data://') === 0) {
                $response = file_get_contents($uri);

                return Create::promiseFor(
                    new Response(
                        200,
                        [],
                        $response
                    )
                );
            }

            throw new \RuntimeException(sprintf('No Response queued for URI "%s"', $uri), 1624304664);
        };

        $handlerStack = HandlerStack::create($requestHandler);
        $handlerStack->push(Middleware::history($this->history));

        return new Client([
            'handler' => $handlerStack
        ]);
    }

    public function queueResponse(UriInterface $uri, ResponseInterface $response): void
    {
        $this->responsesForUris[(string)$uri] = $response;
    }

    public function getHistory(): array
    {
        return $this->history;
    }

    public function popRequest(): Request
    {
        if (count($this->history) === 0) {
            throw new \RuntimeException('History is empty!', 1594219043);
        }

        $entry = array_pop($this->history);

        return $entry['request'];
    }

    public function shiftRequest(): Request
    {
        if (count($this->history) === 0) {
            throw new \RuntimeException('History is empty!', 1594219043);
        }

        $entry = array_shift($this->history);

        return $entry['request'];
    }

}
