<?php

namespace Netlogix\JsonApiOrg\Consumer\Service;

/*
 * This file is part of the Netlogix.JsonApiOrg.Consumer package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use GuzzleHttp\Client;
use Neos\Cache\Exception\InvalidDataException;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Helper\UriHelper;
use Neos\Flow\Http\Uri;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\Arguments\PageInterface;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\Arguments\SortInterface;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\ResourceProxy;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\ResourceProxyIterator;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\Type;
use Netlogix\JsonApiOrg\Consumer\Guzzle\ClientProvider;

/**
 * @Flow\Scope("singleton")
 */
class ConsumerBackend implements ConsumerBackendInterface
{

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
     * @var StringFrontend
     * @Flow\Inject
     */
    protected $requestsCache;

    /**
     * @var ClientProvider
     * @Flow\Inject
     */
    protected $clientProvider;

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

        return null;
    }

    /**
     * @param Uri $endpointDiscovery
     * @throws InvalidDataException
     * @throws \Neos\Cache\Exception
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
     * @param PageInterface|null $page
     * @param SortInterface|null $sort
     * @return ResourceProxyIterator
     * @throws InvalidDataException
     * @throws \Neos\Cache\Exception
     */
    public function findByTypeAndFilter(
        $type,
        $filter = [],
        $include = [],
        PageInterface $page = null,
        SortInterface $sort = null
    ) {
        $queryUri = $this->getQueryUriForFindByTypeAndFilter(
            $type,
            $filter,
            $include,
            $page,
            $sort
        );
        return $this->fetchFromUri($queryUri);
    }

    /**
     * @param string $type
     * @param array $filter
     * @param array $include
     * @param PageInterface|null $page
     * @param SortInterface|null $sort
     * @return Uri
     */
    public function getQueryUriForFindByTypeAndFilter(
        $type,
        $filter = [],
        $include = [],
        PageInterface $page = null,
        SortInterface $sort = null
    ): Uri {
        $type = $this->getType($type);

        $arguments = UriHelper::parseQueryIntoArguments($type->getUri());
        foreach ($filter as $key => $value) {
            $arguments['filter'][$key] = $value;
        }
        $include = array_unique(array_merge($type->getDefaultIncludes(), $include));
        $arguments['include'] = join(',', $include);
        if (!$arguments['include']) {
            unset($arguments['include']);
        }
        if ($page !== null) {
            $arguments['page'] = $page->__toArray();
        }
        if ($sort !== null) {
            $arguments['sort'] = $sort->__toString();
        }

        $queryUri = UriHelper::uriWithArguments($type->getUri(), $arguments);
        assert($queryUri instanceof Uri);

        return $queryUri;
    }

    /**
     * @param Uri $queryUri
     * @return ResourceProxyIterator
     * @throws InvalidDataException
     * @throws \Neos\Cache\Exception
     */
    public function fetchFromUri(Uri $queryUri)
    {
        $resourceProxy = ResourceProxyIterator::fromUri($queryUri);
        $resourceProxy = $resourceProxy->loadFromCache();

        if (!$resourceProxy->hasResults()) {
            $jsonResult = $this->requestJson($queryUri);
            $resourceProxy = $resourceProxy->withJsonResult($jsonResult);
        }

        $convertResourceDefinitionToResourceProxy = function (array $resourceDefinition): ?ResourceProxy {
            return $this->convertResourceDefinitionToResourceProxy($resourceDefinition);
        };
        $resourceProxy->initialize($convertResourceDefinitionToResourceProxy);
        return $resourceProxy;
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
     * @throws InvalidDataException
     * @throws \Neos\Cache\Exception
     */
    protected function requestJson(Uri $uri)
    {
        $uriString = (string)$uri;

        $headers = $this->getRequestHeaders($uriString);
        $headersForCacheIdentifier = [];
        foreach ($headers as $key => $value) {
            $headersForCacheIdentifier[] = sprintf('%s: %s', $key, $value);
        }
        $cacheIdentifier = md5(serialize($headersForCacheIdentifier) . '|' . $uriString);

        if ($this->requestsCache->has($cacheIdentifier)) {
            $result = $this->requestsCache->get($cacheIdentifier);
        } else {
            $result = $this->fetch($uri, $headers);
            $this->requestsCache->set($cacheIdentifier, $result);
        }

        return json_decode($result, true);
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
     * @param Uri $uri
     * @param array<string, string> $headers
     * @return string
     */
    protected function fetch(Uri $uri, array $headers = [])
    {
        $client = $this->clientProvider->createClient();

        $response = $client->get($uri, [
            'headers' => $headers
        ]);

        return $response->getBody()->getContents();
    }

    protected function convertResourceDefinitionToResourceProxy(array $resourceDefinition): ?ResourceProxy
    {
        $typeName = $resourceDefinition['type'];
        $id = $resourceDefinition['id'];
        $resource = $this->getResourceProxyFromCache($typeName, $id);
        if (!$resource) {
            $type = $this->getType($typeName);
            if (!$type) {
                return null;
            }
            $resource = $type->createEmptyResource();
            $cacheIdentifier = $this->calculateCacheIdentifier($typeName, $id);
            $this->resources[$cacheIdentifier] = $resource;
        }
        $resource->setPayload($resourceDefinition);
        return $resource;
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

        return null;
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

    protected function createClient(): Client
    {
        return $this->clientProvider->createClient();
    }
}
