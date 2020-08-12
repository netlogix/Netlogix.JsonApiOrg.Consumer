<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Tests\Functional;

use Netlogix\JsonApiOrg\Consumer\Domain\Model as JsonApi;

class CachingTest extends FunctionalTestCase
{
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
}
