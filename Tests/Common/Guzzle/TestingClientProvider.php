<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Tests\Common\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neos\Cache\Frontend\FrontendInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Core\Bootstrap;
use Netlogix\JsonApiOrg\Consumer\Guzzle\ClientProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * @Flow\Scope("singleton")
 */
final class TestingClientProvider implements ClientProvider
{

    private const CACHE_IDENTIFIER = 'NetlogixJsonApiOrgConsumer_HistoryMiddlewareCache';

    /**
     * @var array
     */
    private $history = [];

    private FrontendInterface $cache;

    public function __construct()
    {
        $cacheManager = Bootstrap::$staticObjectManager->get(CacheManager::class);
        $this->cache = $cacheManager->getCache(self::CACHE_IDENTIFIER);
    }

    public function createClient(): Client
    {
        $requestHandler = function (RequestInterface $request) {
            $requestUri = $request->getUri();
            if ($requestUri->getQuery() && strpos($requestUri->getQuery(), '[') !== false) {
                // url was crafted manually without encoding the query
                $requestUri = $requestUri->withQuery(urlencode($requestUri->getQuery()));
            }
            $uri = (string) $requestUri;
            $cacheIdentifier = self::cacheIdentifier($requestUri);

            if ($this->cache->has($cacheIdentifier)) {
                $response = Message::parseResponse(
                    $this->cache->get($cacheIdentifier)
                );
                return Create::promiseFor($response);
            }

            if (strpos($uri, 'resource://') === 0
                || strpos($uri, 'data://') === 0) {
                $uri = (string) $requestUri
                    ->withQuery('')
                    ->withFragment('');
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
        $this
            ->cache
            ->set(
                self::cacheIdentifier($uri),
                Message::toString($response)
            );
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

    public function flush(): void
    {
        $this->cache->flush();
    }

    private static function cacheIdentifier(UriInterface $uri): string
    {
        return sha1($uri->__toString());
    }

}
