<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Tests\Functional;

use Neos\Cache\Backend\WithSetupInterface;
use Neos\Cache\Frontend\FrontendInterface;
use Neos\Flow\Cache\CacheManager;
use Netlogix\JsonApiOrg\Consumer\Domain\Model as JsonApi;

class CachingTest extends FunctionalTestCase
{

    public function setUp(): void
    {
        parent::setUp();

        $cacheManager = $this->objectManager->get(CacheManager::class);
        assert($cacheManager instanceof CacheManager);

        $cache = $cacheManager->getCache('NetlogixJsonApiOrgConsumer_ResultsCache');
        if ($cache->getBackend() instanceof WithSetupInterface) {
            $cache->getBackend()->setup();
        }
    }

    /**
     * @test
     * @dataProvider provideJsonResponse
     */
    public function JSON_data_can_be_saved_to_cache(array $jsonApiFixture)
    {
        $uri = $this->asDataUri([]);

        JsonApi\ResourceProxyIterator::fromUri($uri)
            ->withJsonResult($jsonApiFixture)
            ->saveToCache(1000);

        $result = $this->consumerBackend
            ->fetchFromUri($uri)
            ->getArrayCopy();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(JsonApi\ResourceProxy::class, current($result));
    }

    /**
     * @test
     * @dataProvider provideJsonResponse
     */
    public function Cached_data_respects_cache_lifetime(array $jsonApiFixture)
    {
        $uri = $this->asDataUri([]);

        JsonApi\ResourceProxyIterator::fromUri($uri)
            ->withJsonResult($jsonApiFixture)
            ->saveToCache(1);

        sleep(2);

        $result = $this->consumerBackend
            ->fetchFromUri($uri)
            ->getArrayCopy();

        $this->assertCount(0, $result);
    }

    /**
     * @test
     * @dataProvider provideJsonResponse
     */
    public function Cached_data_will_not_be_written_to_cache_again_with_same_lifetime_and_tags(array $jsonApiFixture)
    {
        $uri = $this->asDataUri([]);
        $tags = ['foo', 'bar', 'baz'];

        JsonApi\ResourceProxyIterator::fromUri($uri)
            ->withJsonResult($jsonApiFixture)
            ->saveToCache(50, ...$tags);

        sleep(2);

        $proxy = $this->consumerBackend
            ->fetchFromUri($uri);

        $cache = $this->getMockBuilder(FrontendInterface::class)
            ->getMock();

        $cache
            ->expects(self::never())
            ->method('set');

        $this->inject($proxy, 'cache', $cache);

        $proxy->saveToCache(50, ...$tags);
    }

    /**
     * @test
     * @dataProvider provideJsonResponse
     */
    public function Cached_data_will_not_be_written_to_cache_again_with_same_lifetime_and_differently_ordered_tags(array $jsonApiFixture)
    {
        $uri = $this->asDataUri([]);
        $tags = ['foo', 'bar', 'baz'];

        JsonApi\ResourceProxyIterator::fromUri($uri)
            ->withJsonResult($jsonApiFixture)
            ->saveToCache(50, ...$tags);

        sleep(2);

        $proxy = $this->consumerBackend
            ->fetchFromUri($uri);

        $cache = $this->getMockBuilder(FrontendInterface::class)
            ->getMock();

        $cache
            ->expects(self::never())
            ->method('set');

        $this->inject($proxy, 'cache', $cache);

        $proxy->saveToCache(50, 'baz', 'bar', 'foo');
    }

    /**
     * @test
     * @dataProvider provideJsonResponse
     */
    public function Cached_data_will_be_written_to_cache_again_if_lifetime_differs(array $jsonApiFixture)
    {
        $uri = $this->asDataUri([]);
        $tags = ['foo', 'bar', 'baz'];

        JsonApi\ResourceProxyIterator::fromUri($uri)
            ->withJsonResult($jsonApiFixture)
            ->saveToCache(50, ...$tags);

        sleep(2);

        $proxy = $this->consumerBackend
            ->fetchFromUri($uri);

        $cache = $this->getMockBuilder(FrontendInterface::class)
            ->getMock();

        $cache
            ->expects(self::once())
            ->method('set');

        $this->inject($proxy, 'cache', $cache);

        $proxy->saveToCache(25, ...$tags);
    }

    /**
     * @test
     * @dataProvider provideJsonResponse
     */
    public function Cached_data_will_be_written_to_cache_again_if_tags_differs(array $jsonApiFixture)
    {
        $uri = $this->asDataUri([]);
        $tags = ['foo', 'bar', 'baz'];

        JsonApi\ResourceProxyIterator::fromUri($uri)
            ->withJsonResult($jsonApiFixture)
            ->saveToCache(50, ...$tags);

        sleep(2);

        $proxy = $this->consumerBackend
            ->fetchFromUri($uri);

        $cache = $this->getMockBuilder(FrontendInterface::class)
            ->getMock();

        $cache
            ->expects(self::once())
            ->method('set');

        $this->inject($proxy, 'cache', $cache);

        $proxy->saveToCache(50, 'foo1', 'bar1', 'baz1');
    }
}
