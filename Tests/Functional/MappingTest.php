<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Tests\Functional;

use Netlogix\JsonApiOrg\Consumer\Domain\Model as JsonApi;

class MappingTest extends FunctionalTestCase
{
    /**
     * @test
     * @dataProvider provideJsonResponse
     */
    public function JSON_data_can_be_transformed_into_ResourceProxy_objects(array $jsonApiFixture)
    {
        $uri = self::asDataUri(
            $jsonApiFixture
        );

        $result = $this->consumerBackend
            ->fetchFromUri($uri)
            ->getArrayCopy();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(JsonApi\ResourceProxy::class, current($result));
    }

    /**
     * @test
     */
    public function ResourceProxy_objects_can_have_attributes()
    {
        $jsonApiFixture = [
            'data' => [
                'type' => self::TYPE_NAME,
                'id' => 1,
                'attributes' => [
                    'attr' => 'attribute value'
                ]
            ]
        ];

        $uri = self::asDataUri([]);

        JsonApi\ResourceProxyIterator::fromUri($uri)
            ->withJsonResult($jsonApiFixture)
            ->saveToCache(100);

        $result = $this->consumerBackend
            ->fetchFromUri($uri)
            ->getIterator()
            ->current();

        assert($result instanceof JsonApi\ResourceProxy);
        $this->assertEquals('attribute value', $result->offsetGet('attr'));
    }

    /**
     * @test
     */
    public function ResourceProxy_objects_can_have_relations_of_type_single()
    {
        $second = [
            'type' => self::TYPE_NAME,
            'id' => 2,
        ];

        $jsonApiFixture = [
            'data' => [
                'type' => self::TYPE_NAME,
                'id' => 1,
                'relationships' => [
                    'single' => [
                        'data' => $second
                    ]
                ]
            ],
            'included' => [
                $second
            ]
        ];

        $uri = self::asDataUri([]);

        JsonApi\ResourceProxyIterator::fromUri($uri)
            ->withJsonResult($jsonApiFixture)
            ->saveToCache(100);

        $result = $this->consumerBackend
            ->fetchFromUri($uri)
            ->getIterator()
            ->current();

        assert($result instanceof JsonApi\ResourceProxy);
        $this->assertInstanceOf(JsonApi\ResourceProxy::class, $result->offsetGet('single'));
    }

    /**
     * @test
     */
    public function ResourceProxy_objects_can_have_relations_of_type_collection()
    {
        $second = [
            'type' => self::TYPE_NAME,
            'id' => 2,
        ];
        $third = [
            'type' => self::TYPE_NAME,
            'id' => 3,
        ];

        $jsonApiFixture = [
            'data' => [
                'type' => self::TYPE_NAME,
                'id' => 1,
                'relationships' => [
                    'collection' => [
                        'data' => [
                            $second,
                            $third,
                        ]
                    ]
                ]
            ],
            'included' => [
                $second,
                $third,
            ]
        ];

        $uri = self::asDataUri([]);

        JsonApi\ResourceProxyIterator::fromUri($uri)
            ->withJsonResult($jsonApiFixture)
            ->saveToCache(100);

        $result = $this->consumerBackend
            ->fetchFromUri($uri)
            ->getIterator()
            ->current();

        assert($result instanceof JsonApi\ResourceProxy);

        $collection = $result->offsetGet('collection');
        $this->assertCount(2, $collection);
        foreach ($collection as $related) {
            $this->assertInstanceOf(JsonApi\ResourceProxy::class, $related);
        }
    }
}
