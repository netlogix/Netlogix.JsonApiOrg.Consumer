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

class EndpointCacheMiddleware
{

    protected array $httpMethods = ['GET' => true];

    /**
     * @var VariableFrontend
     * @Flow\Inject
     */
    protected $cache;

    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use (&$handler) {
            if (!isset($this->httpMethods[strtoupper($request->getMethod())])) {
                // No caching for this method allowed
                return $handler($request, $options);
            }

            $requestPath = $request->getUri()->getPath() ?: '';
            if (!self::str_ends_with(rtrim($requestPath, '/'), '/.well-known/endpoint-discovery')) {
                return $handler($request, $options);
            }

            $cacheIdentifier = self::getCacheKey($request);

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
                        'body' => (string)$response->getBody()->getContents()
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

    private static function str_ends_with(string $haystack, string $needle)
    {
        $haystackLength = strlen($haystack);
        $needleLength = strlen($needle);
        if (!$haystackLength || $needleLength > $haystackLength) {
            return false;
        }
        $position = strrpos($haystack, $needle);
        return $position !== false && $position === $haystackLength - $needleLength;
    }

    private static function getCacheKey(RequestInterface $request)
    {
        return hash('sha256', $request->getMethod() . $request->getUri() . json_encode($request->getHeaders()));
    }
}
