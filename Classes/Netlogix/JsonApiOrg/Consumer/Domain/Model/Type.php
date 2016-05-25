<?php
namespace Netlogix\JsonApiOrg\Consumer\Domain\Model;

/*
 * This file is part of the Netlogix.JsonApiOrg.Consumer package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Http\Uri;

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
    protected $uri = '';

    /**
     * @param string $typeName
     * @param string $resourceClassName
     * @param array $properties
     * @param Uri $uri
     */
    public function __construct($typeName, $resourceClassName = ResourceProxy::class, array $properties = [], Uri $uri = null)
    {
        $this->typeName = (string)$typeName;
        $this->resourceClassName = (string)$resourceClassName;
        $this->properties = $properties;
        if ($uri) {
            $this->setUri($uri);
        }
    }

    /**
     * @return Uri
     */
    public function setUri(Uri $uri)
    {
        $this->uri = $uri;
    }

    /**
     * @return Uri
     */
    public function getUri()
    {
        return $this->uri;
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