<?php

namespace Netlogix\JsonApiOrg\Consumer\Domain\Model;

/*
 * This file is part of the Netlogix.JsonApiOrg.Consumer package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Psr\Http\Message\UriInterface;

use function array_filter;
use function array_map;
use function array_values;
use function current;
use function is_callable;

use const ARRAY_FILTER_USE_BOTH;

class Type
{
    /**
     * That's the return value for undefined properties.
     */
    public const null PROPERTY_UNDEFINED = null;

    /**
     * Those properties are "attributes" as of jsonapi.org
     */
    public const string PROPERTY_ATTRIBUTE = 'attribute';

    /**
     * Those properties are a relationship to a single Resource
     */
    public const string PROPERTY_SINGLE_RELATIONSHIP = 'single';

    /**
     * Those properties are a relationship to a collection of other resources
     */
    public const string PROPERTY_COLLECTION_RELATIONSHIP = 'collection';

    /**
     * That's not a real property but maybe a getter in the corresponding model.
     */
    public const string PROPERTY_CUSTOM = 'custom';

    public const string DEFAULT_ENDPOINT_NAME = 'default';

    /**
     * @var UriInterface[]
     */
    protected array $uris = [];

    #[Flow\Inject]
    protected ObjectManagerInterface $objectManager;

    protected function __construct(
        protected string $typeName,
        /**
         * @var class-string<ResourceProxy>
         */
        protected string $resourceClassName,
        protected array $properties,
        protected array $defaultIncludes,
    ) {
    }

    public static function create(
        string $typeName,
        /**
         * @var class-string<ResourceProxy>
         */
        string $resourceClassName = ResourceProxy::class,
        array $properties = [],
        array $defaultIncludes = []
    ): static {
        return new static(
            $typeName,
            $resourceClassName,
            $properties,
            $defaultIncludes
        );
    }

    public function getUri(string $endpointName = self::DEFAULT_ENDPOINT_NAME): ?UriInterface
    {
        return $this->uris[$endpointName]
            ?? $this->uris[self::DEFAULT_ENDPOINT_NAME]
            ?? current($this->uris)
            ?: null;
    }

    /**
     * @return Type[]
     */
    public function getUriVariants(?callable $filter = null): array
    {
        $uris = [];
        foreach ($this->uris as $uri) {
            $uris[(string) $uri] = $uri;
        }
        $variants = array_map(fn (UriInterface $uri) => (clone $this)->setUri($uri), $uris);
        $variants = is_callable($filter) ? array_filter($variants, $filter, ARRAY_FILTER_USE_BOTH) : $variants;
        $variants = array_values($variants);
        return $variants;
    }

    public function addUri(UriInterface $uri, string $endpointName = self::DEFAULT_ENDPOINT_NAME): static
    {
        $this->uris[$endpointName] = $uri;
        return $this;
    }

    public function setUri(UriInterface $uri, string $endpointName = self::DEFAULT_ENDPOINT_NAME): static
    {
        $this->uris = [$endpointName => $uri];
        return $this;
    }

    /**
     * @return string
     */
    public function getTypeName()
    {
        return $this->typeName;
    }

    /**
     * @return string
     */
    public function getResourceClassName()
    {
        return $this->resourceClassName;
    }

    /**
     * @return ResourceProxy
     */
    public function createEmptyResource(): ResourceProxy
    {
        $result = $this->objectManager->get($this->resourceClassName, $this);
        assert($result instanceof ResourceProxy);
        return $result;
    }

    /**
     * @return string[]
     */
    public function getDefaultIncludes(): array
    {
        return $this->defaultIncludes;
    }

    /**
     * @return self::PROPERTY_UNDEFINED|self::PROPERTY_ATTRIBUTE|self::PROPERTY_SINGLE_RELATIONSHIP|self::PROPERTY_COLLECTION_RELATIONSHIP|self::PROPERTY_CUSTOM
     */
    public function getPropertyDefinition($propertyName)
    {
        if (!array_key_exists($propertyName, $this->properties)) {
            return self::PROPERTY_UNDEFINED;
        } else {
            return $this->properties[$propertyName];
        }
    }
}