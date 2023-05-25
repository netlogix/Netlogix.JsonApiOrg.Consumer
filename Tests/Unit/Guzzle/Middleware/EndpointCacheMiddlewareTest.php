<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Tests\Unit\Guzzle\Middleware;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Tests\UnitTestCase;
use Netlogix\JsonApiOrg\Consumer\Guzzle\Middleware\EndpointCacheMiddleware;

class EndpointCacheMiddlewareTest extends UnitTestCase
{

    /**
     * @var VariableFrontend
     */
    protected $cache;

    /**
     * @var EndpointCacheMiddleware
     */
    protected $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->createMock(VariableFrontend::class);
        $this->middleware = new EndpointCacheMiddleware();
        $this->inject($this->middleware, 'cache', $this->cache);
    }

    /**
     * @test
     */
    public function Cache_only_safe_methods()
    {
        $request = new Request('POST', '/uri');
        $options = [];

        $mock = $this->getMockBuilder(ClosureLike::class)->getMock();
        $mock->expects($this->once())
            ->method('__invoke')
            ->with($request, $options);

        $middleware = $this->middleware->__invoke(\Closure::fromCallable($mock));
        $middleware($request, $options);
    }

    /**
     * @test
     */
    public function Cache_only_endpoint_discovery()
    {
        $request = new Request('GET', '/uri');
        $options = [];

        $mock = $this->getMockBuilder(ClosureLike::class)->getMock();
        $mock->expects($this->once())
            ->method('__invoke')
            ->with($request, $options);

        $middleware = $this->middleware->__invoke(\Closure::fromCallable($mock));
        $middleware($request, $options);
    }

    /**
     * @test
     */
    public function Empty_paths_dont_cause_exception()
    {
        $uri = new Uri('https://foo');
        $request = new Request('GET', $uri);

        $this->assertEmpty($uri->getPath());

        $options = [];

        $mock = $this->getMockBuilder(ClosureLike::class)->getMock();
        $mock->expects($this->once())
            ->method('__invoke')
            ->with($request, $options);

        $middleware = $this->middleware->__invoke(\Closure::fromCallable($mock));
        $middleware($request, $options);
    }

    /**
     * @test
     */
    public function Endpoint_discovery_result_is_written_to_cache()
    {
        $request = new Request('GET', '/foo/.well-known/endpoint-discovery');
        $response = $this->getResponse();
        $options = [];

        $promise = $this->createMock(PromiseInterface::class);
        $promise
            ->expects($this->once())
            ->method('then')
            ->will($this->returnCallback(function (\Closure $callback) use ($response) {
                return new FulfilledPromise($callback($response));
            }));

        $mock = $this->getMockBuilder(ClosureLike::class)->getMock();
        $mock->expects($this->once())
            ->method('__invoke')
            ->with($request, $options)
            ->willReturn($promise);

        $this->cache->expects($this->once())
            ->method('has');

        $this->cache->expects($this->once())
            ->method('set');

        $middleware = $this->middleware->__invoke(\Closure::fromCallable($mock));
        $result = $middleware($request, $options);
    }

    /**
     * @test
     */
    public function Endpoint_discovery_result_is_read_from_cache()
    {
        $request = new Request('GET', '/foo/.well-known/endpoint-discovery');
        $response = $this->getResponse();
        $options = [];

        $mock = $this->getMockBuilder(ClosureLike::class)->getMock();
        $mock->expects($this->never())
            ->method('__invoke');

        $this->cache->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn([
                'headers' => $response->getHeaders(),
                'body' => (string)$response->getBody()->getContents()
            ]);

        $middleware = $this->middleware->__invoke(\Closure::fromCallable($mock));
        $result = $middleware($request, $options);

        $this->assertInstanceOf(FulfilledPromise::class, $result);
    }

    /**
     * @test
     */
    public function Only_200_status_code_is_cached()
    {
        $request = new Request('GET', '/foo/.well-known/endpoint-discovery');
        $response = $this->getResponse(203);
        $options = [];

        $promise = $this->createMock(PromiseInterface::class);
        $promise
            ->expects($this->once())
            ->method('then')
            ->willReturn(new FulfilledPromise($response));

        $mock = $this->getMockBuilder(ClosureLike::class)->getMock();
        $mock->expects($this->once())
            ->method('__invoke')
            ->with($request, $options)
            ->willReturn($promise);

        $this->cache->expects($this->never())
            ->method('set');

        $middleware = $this->middleware->__invoke(\Closure::fromCallable($mock));
        $middleware($request, $options);
    }

    private function getResponse($status = 200): Response
    {
        $body = $this->getBody();
        $headers = [
            'content-type' => 'application/json'
        ];
        return new Response($status, $headers, json_encode($body), '1.1');
    }

    private function getBody()
    {
        return [
            'meta' => [],
            'links' => [
                [
                    'href' => 'https=>\/\/www.example.com\/foo\/bar',
                    'meta' => [
                        'type' => 'resourceUri',
                        'resourceType' => 'foo\/bar',
                        'packageKey' => 'Netlogix.DummyResource'
                    ]
                ]
            ]
        ];
    }

}