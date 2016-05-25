<?php
namespace Netlogix\JsonApiOrg\Consumer\Domain\Model;

/*
 * This file is part of the Netlogix.JsonApiOrg.Consumer package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Netlogix\JsonApiOrg\Consumer\Service\ConsumerBackendInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Http\Uri;
use TYPO3\Flow\Reflection\ObjectAccess;

class ResourceProxy implements \ArrayAccess
{
    /**
     * @var ConsumerBackendInterface
     * @Flow\Inject
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

    public function __construct(Type $type)
    {
        $this->type = $type;
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
                return $this->payload['attributes'][$propertyName];
                break;
            case Type::PROPERTY_SINGLE_RELATIONSHIP:
                $this->loadRelationship($propertyName);
                $payload = $this->payload['relationships'][$propertyName]['data'];
                return $payload ? $this->consumerBackend->fetchByTypeAndId($payload['type'], $payload['id']) : null;
            case Type::PROPERTY_COLLECTION_RELATIONSHIP:
                $this->loadRelationship($propertyName);
                $result = [];
                foreach ($this->payload['relationships'][$propertyName]['data'] as $payload) {
                    $result[] = $this->consumerBackend->fetchByTypeAndId($payload['type'], $payload['id']);
                }
                return $result;
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

    /**
     * @param array<string> $nameComponents
     * @return array
     */
    protected function getNotEmptyOffsetComponents(array $nameComponents = [])
    {
        $result = [];
        foreach ($nameComponents as $nameComponent) {
            $value = ObjectAccess::getProperty($this, $nameComponent);
            if ($value) {
                $result[] = $value;
            }
        }
        return $result;
    }

    /**
     * @param string $propertyName
     */
    protected function loadRelationship($propertyName)
    {
        if (array_key_exists('data', $this->payload['relationships'][$propertyName])) {
            return;
        }
        $this->payload['relationships'][$propertyName]['data'] = null;

        $queryResult = $this->consumerBackend->fetchFromUri(new Uri($this->payload['relationships'][$propertyName]['links']['related']));

        if ($this->type->getPropertyDefinition($propertyName) === Type::PROPERTY_COLLECTION_RELATIONSHIP) {
            $this->payload['relationships'][$propertyName]['data'] = [];
            /** @var ResourceProxy $resource */
            foreach ($queryResult as $resource) {
                $this->payload['relationships'][$propertyName]['data'][] = $resource->getJsonApiOrgResourceIdentifier();
            }
        } elseif ($this->type->getPropertyDefinition($propertyName) === Type::PROPERTY_COLLECTION_RELATIONSHIP) {
            /** @var ResourceProxy $queryResult */
            $this->payload['relationships'][$propertyName]['data']= $queryResult->getJsonApiOrgResourceIdentifier();
        }
    }

}