<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Tests\Functional;

use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Tests\FunctionalTestCase as BaseTestCase;
use Netlogix\JsonApiOrg\Consumer\Domain\Model as JsonApi;
use Netlogix\JsonApiOrg\Consumer\Service\ConsumerBackend;
use Psr\Http\Message\UriInterface;

class FunctionalTestCase extends BaseTestCase
{
    const TYPE_NAME = 'some/type';

    /**
     * @var JsonApi\Type
     */
    protected $type;

    /**
     * @var ConsumerBackend
     */
    protected $consumerBackend;

    public function setUp(): void
    {
        parent::setUp();

        $typeName = self::TYPE_NAME;
        $className = JsonApi\ResourceProxy::class;
        $properties = [
            'attr' => JsonApi\Type::PROPERTY_ATTRIBUTE,
            'single' => JsonApi\Type::PROPERTY_SINGLE_RELATIONSHIP,
            'collection' => JsonApi\Type::PROPERTY_COLLECTION_RELATIONSHIP,
        ];

        $this->type = new class ($typeName, $className, $properties, []) extends JsonApi\Type {
            public $__consumerBackend;

            public function createEmptyResource(): JsonApi\ResourceProxy
            {
                return new class ($this, $this->__consumerBackend) extends JsonApi\ResourceProxy {
                };
            }
        };

        $this->consumerBackend = new ConsumerBackend();
        $this->consumerBackend->addType($this->type);
        $this->type->__consumerBackend = $this->consumerBackend;
    }

    public function asDataUri(array $fixture): UriInterface
    {
        $dataUri = 'data:application/json;base64,' . base64_encode(json_encode($fixture));
        return new Uri($dataUri);
    }

    public static function provideJsonResponse()
    {
        $entity = [
            'id' => 0,
            'type' => self::TYPE_NAME
        ];

        $singleObjectResult = [
            'data' => $entity
        ];
        yield 'single related object' => [$singleObjectResult];

        $collectionOfObjects = [
            'data' => [$entity]
        ];
        yield 'collection of related objects' => [$collectionOfObjects];
    }
}
