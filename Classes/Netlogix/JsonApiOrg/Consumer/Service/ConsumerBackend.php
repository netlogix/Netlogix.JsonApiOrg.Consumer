<?php
namespace Netlogix\JsonApiOrg\Consumer\Service;

/*
 * This file is part of the Netlogix.JsonApiOrg.Consumer package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Netlogix\JsonApiOrg\Consumer\Domain\Model\ResourceProxy;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\Type;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cache\Frontend\StringFrontend;
use TYPO3\Flow\Http\Uri;
use TYPO3\Flow\Object\ObjectManagerInterface;

/**
 * @Flow\Scope("singleton")
 */
class ConsumerBackend implements ConsumerBackendInterface
{
    /**
     * @var ObjectManagerInterface
     * @Flow\Inject
     */
    protected $objectManager;

    /**
     * @var StringFrontend
     * @Flow\Inject
     */
    protected $requestsCache;

    /**
     * @var array<string>
     * @Flow\InjectConfiguration(package = "Netlogix.JsonApiOrg.Consumer", path = "headers")
     */
    protected $headers = [
        '%^http(s?)://%i' => [
            'User-Agent' => 'Netlogix.JsonApiOrg.Consumer'
        ]
    ];

    /**
     * @var array<Type>
     */
    protected $types = [];

    /**
     * @var array<ResourceProxy>
     */
    protected $resources = [];

    /**
     * @param Type $type
     */
    public function addType(Type $type)
    {
        $this->types[$type->getTypeName()] = $type;
    }

    /**
     * @param string $typeName
     * @return Type|null
     */
    public function getType($typeName)
    {
        if (array_key_exists($typeName, $this->types)) {
            return $this->types[$typeName];
        }
    }

    /**
     * @param Uri $endpointDiscovery
     */
    public function registerEndpointsByEndpointDiscovery(Uri $endpointDiscovery)
    {
        $result = $this->requestJson($endpointDiscovery);
        foreach ($result['links'] as $link) {
            if (!is_array($link) || !isset($link['meta'])) {
                continue;
            }
            if (!isset($link['meta']['type']) || $link['meta']['type'] !== 'resourceUri') {
                continue;
            }
            if (!isset($link['meta']['resourceType'])) {
                continue;
            }
            if (!isset($link['href'])) {
                continue;
            }

            $typeName = $link['meta']['resourceType'];
            $type = $this->getType($typeName);
            if (!$type) {
                continue;
            }
            $type->setUri(new Uri($link['href']));
        }
    }

    /**
     * @param string $type
     * @param array $filter
     * @param array $include
     * @return array<ResourceProxy>
     */
    public function findByTypeAndFilter($type, $filter = [], $include = [])
    {
        $type = $this->getType($type);
        $queryUri = clone $type->getUri();

        $arguments = $queryUri->getArguments();
        foreach ($filter as $key => $value) {
            $arguments['filter'][$key] = $value;
        }
        $include = array_merge($type->getDefaultIncludes(), $include);
        $arguments['include'] = join(',', $include);
        if (!$arguments['include']) {
            unset($arguments['include']);
        }
        $queryUri->setQuery(http_build_query($arguments));

        return $this->fetchFromUri($queryUri);
    }

    /**
     * @param Uri $queryUri
     * @return array<ResourceProxy>|ResourceProxy
     */
    public function fetchFromUri(Uri $queryUri)
    {
        $jsonResult = $this->requestJson($queryUri);
        $this->addJsonResultToCache($jsonResult);
        if (!array_key_exists('data', $jsonResult)) {
            return [];
        }

        if (array_key_exists('type', $jsonResult['data']) && array_key_exists('id', $jsonResult['data'])) {
            return $this->getResourceProxyFromCache($jsonResult['data']['type'], $jsonResult['data']['id']);

        } else {
            $result = [];
            if (array_key_exists('data', $jsonResult)) {
                foreach ($jsonResult['data'] as $resourceDefinition) {
                    $resource = $this->getResourceProxyFromCache($resourceDefinition['type'], $resourceDefinition['id']);
                    if ($resource) {
                        $result[] = $resource;
                    }
                }
            }
            return $result;
        }
    }

    /**
     * @param mixed $type
     * @param mixed $id
     * @return ResourceProxy
     */
    public function fetchByTypeAndId($type, $id)
    {
        return $this->getResourceProxyFromCache($type, $id);
    }

    /**
     * @param Uri $uri
     * @return array
     */
    protected function requestJson(Uri $uri)
    {
        $uriString = (string)$uri;

        $headers = $this->getRequestHeaders($uriString);

        $options = [
            'http' => [
                'header' => [],
            ]
        ];
        foreach ($headers as $key => $value) {
            $options['http']['header'][] = sprintf('%s: %s', $key, $value);
        }
        $options['http']['header'] = join("\r\n", $options['http']['header']);

        $cacheIdentifier = md5(serialize($options) . '|' . $uriString);

        if ($this->requestsCache->has($cacheIdentifier)) {
            $result = $this->requestsCache->get($cacheIdentifier);
        } else {
            $result = file_get_contents($uriString, null, $context = stream_context_create($options));
            $this->requestsCache->set($cacheIdentifier, $result);
        }
        return json_decode($result, true);
    }

    /**
     * @param array $result
     */
    protected function addJsonResultToCache(array $result)
    {
        foreach (['data', 'included'] as $slotName) {
            if (!array_key_exists($slotName, $result)) {
                continue;
            }
            foreach ($result[$slotName] as $resourceDefinition) {
                $typeName = $resourceDefinition['type'];
                $id = $resourceDefinition['id'];
                $resource = $this->getResourceProxyFromCache($typeName, $id);
                if (!$resource) {
                    $type = $this->getType($typeName);
                    if (!$type) {
                        continue;
                    }
                    $resource = $this->objectManager->get($type->getResourceClassName(), $type);
                    $cacheIdentifier = $this->calculateCacheIdentifier($typeName, $id);
                    $this->resources[$cacheIdentifier] = $resource;
                }
                $resource->setPayload($resourceDefinition);
            }
        }
    }

    /**
     * @param string $type
     * @param string $id
     * @return ResourceProxy
     */
    protected function getResourceProxyFromCache($type, $id)
    {
        $cacheIdentifier = $this->calculateCacheIdentifier($type, $id);
        if (array_key_exists($cacheIdentifier, $this->resources)) {
            return $this->resources[$cacheIdentifier];
        }
    }

    /**
     * @param string $uriString
     * @return array
     */
    protected function getRequestHeaders(string $uriString): array
    {
        $headers = [];
        foreach ($this->headers as $uriPattern => $headersForUriPattern) {
            if (!preg_match($uriPattern, $uriString)) {
                continue;
            }
            foreach ($headersForUriPattern as $key => $value) {
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param string $type
     * @param string $id
     * @return string
     */
    protected function calculateCacheIdentifier($type, $id)
    {
        return $type . "\n" . $id;
    }
}