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
use Netlogix\JsonApiOrg\Consumer\Service\ConsumerBackendInterface;

class ResourceProxy implements \ArrayAccess
{
    /**
     * @var ConsumerBackendInterface
     */
    protected $consumerBackend;

    /**
     * @var Type
     */
    protected $type;

    /**
     * @var array
     */
    protected $payload = [];

    public function __construct(Type $type, ConsumerBackendInterface $consumerBackend)
    {
        $this->type = $type;
        $this->consumerBackend = $consumerBackend;
    }

    /**
     * @param array $payload
     */
    public function setPayload($payload = [])
    {
        $this->payload = $payload;
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($propertyName)
    {
        return $this->type->getPropertyDefinition($propertyName) !== Type::PROPERTY_UNDEFINED;
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($propertyName)
    {
        switch ($this->type->getPropertyDefinition($propertyName)) {
            case Type::PROPERTY_ATTRIBUTE:
                return $this->getAttribute($propertyName);
            case Type::PROPERTY_SINGLE_RELATIONSHIP:
                return $this->getSingleRelationship($propertyName);
            case Type::PROPERTY_COLLECTION_RELATIONSHIP:
                return $this->getCollectionRelationship($propertyName);
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($propertyName, $value)
    {
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($propertyName)
    {
    }

    /**
     * @return string[]
     */
    public function getJsonApiOrgResourceIdentifier()
    {
        return array_intersect_key($this->payload, ['type' => 'type', 'id' => 'id']);
    }

    protected function loadRelationship(string $propertyName)
    {
        if (array_key_exists('data', $this->payload['relationships'][$propertyName])) {
            return;
        }
        $this->payload['relationships'][$propertyName]['data'] = null;

        $queryResult = $this->consumerBackend->fetchFromUri(new Uri($this->payload['relationships'][$propertyName]['links']['related']));

        if ($this->type->getPropertyDefinition($propertyName) === Type::PROPERTY_COLLECTION_RELATIONSHIP) {
            $this->payload['relationships'][$propertyName]['data'] = [];
            foreach ($queryResult as $resource) {
                assert($resource instanceof ResourceProxy);
                $this->payload['relationships'][$propertyName]['data'][] = $resource->getJsonApiOrgResourceIdentifier();
            }
        } elseif ($this->type->getPropertyDefinition($propertyName) === Type::PROPERTY_COLLECTION_RELATIONSHIP) {
            assert($queryResult instanceof ResourceProxy);
            $this->payload['relationships'][$propertyName]['data'] = $queryResult->getJsonApiOrgResourceIdentifier();
        }
    }

    protected function getAttribute(string $propertyName)
    {
        if ($this->type->getPropertyDefinition($propertyName) !== Type::PROPERTY_ATTRIBUTE) {
            throw new \InvalidArgumentException(sprintf('There is no attribute called %s', $propertyName));
        }
        return $this->payload['attributes'][$propertyName];
    }

    protected function getSingleRelationship(string $propertyName)
    {
        if ($this->type->getPropertyDefinition($propertyName) !== Type::PROPERTY_SINGLE_RELATIONSHIP) {
            throw new \InvalidArgumentException(sprintf('There is no relationship called %s', $propertyName));
        }
        $this->loadRelationship($propertyName);
        $payload = $this->payload['relationships'][$propertyName]['data'];
        return $payload ? $this->consumerBackend->fetchByTypeAndId($payload['type'], $payload['id']) : null;
    }

    protected function getCollectionRelationship(string $propertyName)
    {
        if ($this->type->getPropertyDefinition($propertyName) !== Type::PROPERTY_COLLECTION_RELATIONSHIP) {
            throw new \InvalidArgumentException(sprintf('There is no relationship called %s', $propertyName));
        }
        $this->loadRelationship($propertyName);
        $result = [];
        foreach ($this->payload['relationships'][$propertyName]['data'] as $payload) {
            $result[] = $this->consumerBackend->fetchByTypeAndId($payload['type'], $payload['id']);
        }
        return $result;
    }
}