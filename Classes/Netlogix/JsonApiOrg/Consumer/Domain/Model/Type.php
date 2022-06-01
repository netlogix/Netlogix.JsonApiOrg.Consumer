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
use Neos\Flow\Http\Uri;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

class Type
{
    /**
     * That's the return value for undefined properties.
     */
    const PROPERTY_UNDEFINED = null;

    /**
     * Those properties are "attributes" as of jsonapi.org
     */
    const PROPERTY_ATTRIBUTE = 'attribute';

    /**
     * Those properties are a relationship to a single Resource
     */
    const PROPERTY_SINGLE_RELATIONSHIP = 'single';

    /**
     * Those properties are a relationship to a collection of other resources
     */
    const PROPERTY_COLLECTION_RELATIONSHIP = 'collection';

    /**
     * That's not a real property but maybe a getter in the corresponding model.
     */
    const PROPERTY_CUSTOM = 'custom';

    /**
     * @var string
     */
    protected $typeName = '';

    /**
     * @var string
     */
    protected $resourceClassName = '';

    /**
     * @var array
     */
    protected $properties = [];

    /**
     * @var Uri
     */
    protected $uri;

    /**
     * @var array
     */
    protected $defaultIncludes = [];

    /**
     * @var ObjectManagerInterface
     * @Flow\Inject
     */
    protected $objectgManager;

    /**
     * @param string $typeName
     * @param string $resourceClassName
     * @param array $properties
     * @param Uri $uri
     * @param array $defaultIncludes
     */
    public function __construct(
        $typeName,
        $resourceClassName = ResourceProxy::class,
        array $properties = [],
        Uri $uri = null,
        array $defaultIncludes = []
    ) {
        $this->typeName = (string)$typeName;
        $this->resourceClassName = (string)$resourceClassName;
        $this->properties = $properties;
        if ($uri) {
            $this->setUri($uri);
        }
        $this->defaultIncludes = $defaultIncludes;
    }

    /**
     * @return Uri
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @param Uri $uri
     * @return void
     */
    public function setUri(Uri $uri)
    {
        $this->uri = $uri;
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
        return $this->objectgManager->get($this->resourceClassName, $this);
    }

    /**
     * @return array
     */
    public function getDefaultIncludes()
    {
        return $this->defaultIncludes;
    }

    /**
     * @return string|null
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