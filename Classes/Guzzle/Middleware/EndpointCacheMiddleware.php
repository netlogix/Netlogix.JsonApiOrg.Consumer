<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Guzzle\Middleware;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Neos\Cache\Frontend\VariableFrontend;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Neos\Flow\Annotations as Flow;

use function array_filter;
use function hash;
use function in_array;
use function json_encode;
use function str_contains;

use const ARRAY_FILTER_USE_KEY;

class EndpointCacheMiddleware
{
    /**
     * @var VariableFrontend
     * @Flow\Inject
     */
    protected $cache;

    protected function __construct(
        public readonly array $httpMethods,
        public readonly array $headerNames
    ) {
    }

    public static function create(): static
    {
        return new static([], []);
    }

    public function withHttpMethods(string ...$httpMethods): static
    {
        return new static(
            array_map('strtoupper', $httpMethods),
            $this->headerNames
        );
    }

    public function withHeaderNames(string ...$headerNames): static
    {
        return new static(
            $this->httpMethods,
            $headerNames
        );
    }

    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use (&$handler) {
            if (!in_array(strtoupper($request->getMethod()), $this->httpMethods)) {
                // No caching for this method allowed
                return $handler($request, $options);
            }

            if (!str_contains($request->getUri()->getPath() ?: '', '/.well-known/endpoint-discovery')) {
                return $handler($request, $options);
            }

            $cacheIdentifier = $this->getCacheKey($request);

            if ($this->cache->has($cacheIdentifier)) {
                ['body' => $body, 'headers' => $headers] = $this->cache->get($cacheIdentifier);
                $response = new Response(200, $headers, $body, '1.1');
                return new FulfilledPromise(
                    $response
                );
            }

            $promise = $handler($request, $options);
            assert($promise instanceof PromiseInterface);

            return $promise->then(
                function (ResponseInterface $response) use ($cacheIdentifier) {
                    if ($response->getStatusCode() !== 200) {
                        return $response;
                    }

                    $body = $response->getBody();

                    // If the body is not seekable, we have to replace it by a seekable one
                    if (!$body->isSeekable()) {
                        $response = $response->withBody(
                            Utils::streamFor($body->getContents())
                        );
                    }

                    $this->cache->set($cacheIdentifier, [
                        'headers' => $response->getHeaders(),
                        'body' => (string) $response->getBody()->getContents(),
                    ]);

                    // always rewind back to the start otherwise other middlewares may get empty "content"
                    if ($body->isSeekable()) {
                        $response->getBody()->rewind();
                    }

                    return $response;
                }
            );
        };
    }

    private function getCacheKey(RequestInterface $request): string
    {
        $headers = $request->getHeaders();
        $headers = array_filter(
            $headers,
            fn (string $header) => in_array($header, $this->headerNames),
            ARRAY_FILTER_USE_KEY
        );
        $block = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            $headers,
        ];
        return hash('sha256', json_encode($block));
    }
}
