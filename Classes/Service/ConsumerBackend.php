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
use GuzzleHttp\Exception\ClientException as GuzzleClientException;
use GuzzleHttp\Exception\ServerException as GuzzleServerException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use JsonException;
use Neos\Cache\Exception\InvalidDataException;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Helper\UriHelper;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\Arguments\PageInterface;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\Arguments\SortInterface;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\ResourceProxy;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\ResourceProxyIterator;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\Type;
use Netlogix\JsonApiOrg\Consumer\Exception\Client\ClientException;
use Netlogix\JsonApiOrg\Consumer\Exception\Server\ServerException;
use Netlogix\JsonApiOrg\Consumer\Guzzle\ClientProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Stringable;
use Throwable;

use function is_null;
use function is_string;
use function strtolower;

/**
 * @Flow\Scope("singleton")
 */
class ConsumerBackend implements ConsumerBackendInterface
{

    /**
     * A map of arrays where the keys of the first level is meant as regular expression
     * matching the request URI.
     *
     * @var array<string, array<string, string>>
     * @Flow\InjectConfiguration(package = "Netlogix.JsonApiOrg.Consumer", path = "headers")
     */
    protected $headers = [
        '%^http(s?)://%i' => [
            'User-Agent' => 'Netlogix.JsonApiOrg.Consumer',
        ],
    ];

    /**
     * This array does not map to the request uri in any way. It's just headers being
     * applied to every request.
     *
     * @var array<string, (string|Stringable)>
     * @see self::withHeaders()
     */
    protected $additionalHeaders = [];

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
     * Map of typeName to sparse fieldset
     *
     * @var array<string, string[]>
     */
    protected array $sparseFields = [];

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
     * @param UriInterface $endpointDiscovery
     * @throws InvalidDataException
     * @throws \Neos\Cache\Exception
     */
    public function registerEndpointsByEndpointDiscovery(UriInterface $endpointDiscovery): PromiseInterface
    {
        return $this
            ->requestJson($endpointDiscovery)
            ->then(function (array $result) use ($endpointDiscovery): array {
                $types = [];
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
                    $type->addUri(new Uri($link['href']), $endpointDiscovery);
                    $types[] = $type;
                }
                return $types;
            });
    }

    /**
     * @throws InvalidDataException
     * @throws \Neos\Cache\Exception
     */
    public function findByTypeAndFilter(
        string|Type $type,
        array $filter = [],
        array $include = [],
        ?PageInterface $page = null,
        ?SortInterface $sort = null
    ): ResourceProxyIterator {
        return $this
            ->requestByTypeAndFilter(
                type: $type,
                filter: $filter,
                include: $include,
                page: $page,
                sort: $sort
            )
            ->wait();
    }

    /**
     * @throws InvalidDataException
     * @throws \Neos\Cache\Exception
     */
    public function requestByTypeAndFilter(
        string|Type $type,
        array $filter = [],
        array $include = [],
        ?PageInterface $page = null,
        ?SortInterface $sort = null
    ): PromiseInterface {
        $queryUri = $this->getQueryUriForFindByTypeAndFilter(
            type: $type,
            filter: $filter,
            include: $include,
            page: $page,
            sort: $sort
        );
        return $this->requestFromUri($queryUri);
    }

    public function getQueryUriForFindByTypeAndFilter(
        string|Type $type,
        array $filter = [],
        array $include = [],
        ?PageInterface $page = null,
        ?SortInterface $sort = null
    ): Uri {
        $type = $this->normalizeType($type);
        $typeName = $type->getTypeName();

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

        if (array_key_exists($typeName, $this->sparseFields)) {
            $fields = $this->sparseFields[$typeName];
            $arguments[sprintf('fields[%s]', $typeName)] = join(',', $fields);
        }

        $queryUri = UriHelper::uriWithArguments($type->getUri(), $arguments);
        assert($queryUri instanceof Uri);

        return $queryUri;
    }

    /**
     * Fetch data from the given URI synchronously.
     * The resulting ResourceProxyIterator is fully populated.
     *
     * @param UriInterface $queryUri
     * @return ResourceProxyIterator
     * @throws InvalidDataException
     * @throws \Neos\Cache\Exception
     */
    public function fetchFromUri(UriInterface $queryUri): ResourceProxyIterator
    {
        return $this
            ->requestFromUri($queryUri)
            ->wait();
    }

    /**
     * Fetch data from the given URI asynchronously.
     * The request will be executed immediately.
     *
     * @param UriInterface $queryUri
     * @return PromiseInterface<ResourceProxyIterator>
     * @throws InvalidDataException
     * @throws \Neos\Cache\Exception
     */
    public function requestFromUri(UriInterface $queryUri): PromiseInterface
    {
        $resourceProxy = ResourceProxyIterator::fromUri($queryUri);
        $resourceProxy = $resourceProxy->loadFromCache();

        if (!$resourceProxy->hasResults()) {
            $result = $this
                ->requestJson($queryUri)
                ->then(function (array $jsonResult) use ($resourceProxy) {
                    return $resourceProxy->withJsonResult($jsonResult);
                });
        } else {
            $result = new FulfilledPromise($resourceProxy);
        }

        return $result->then(function (ResourceProxyIterator $resourceProxy) {
            $convertResourceDefinitionToResourceProxy = function (array $resourceDefinition): ?ResourceProxy {
                return $this->convertResourceDefinitionToResourceProxy($resourceDefinition);
            };
            $resourceProxy->initialize($convertResourceDefinitionToResourceProxy);
            return $resourceProxy;
        });
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
     * @template T
     * @param array<string, string[]> $fields
     * @param (callable(self): T) $do
     * @return T
     */
    public function withSparseFields(array $fields, callable $do): mixed
    {
        try {
            $previousFields = $this->sparseFields;
            $this->sparseFields = array_merge($previousFields, $fields);

            return $do($this);
        } finally {
            $this->sparseFields = $previousFields;
        }
    }

    /**
     * @template T
     * @param array<string, (string|Stringable)> $additionalHeaders
     * @param (callable(self): T) $do
     * @return T
     */
    public function withHeaders(array $additionalHeaders, callable $do): mixed
    {
        try {
            $previousHeaders = $this->additionalHeaders;
            $this->additionalHeaders = array_merge($previousHeaders, $additionalHeaders);

            return $do($this);
        } finally {
            $this->additionalHeaders = $previousHeaders;
        }
    }

    /**
     * @param Uri $uri
     * @return PromiseInterface<array>
     * @return PromiseInterface<array>
     * @throws \Neos\Cache\Exception
     * @throws InvalidDataException
     */
    protected function requestJson(Uri $uri): PromiseInterface
    {
        $headers = $this->getRequestHeaders($uri);

        $cacheIdentifier = $this->getCacheIdentifierForUri($uri);

        if ($cacheIdentifier && $this->requestsCache->has($cacheIdentifier)) {
            $response = new FulfilledPromise(
                $this->requestsCache->get($cacheIdentifier)
            );
        } else {
            $response = $this
                ->fetch($uri, $headers)
                ->then(function (string $result) use ($cacheIdentifier) {
                    if ($cacheIdentifier) {
                        $this->requestsCache->set($cacheIdentifier, $result);
                    }
                    return $result;
                });
        }

        return $response->then(function (string $result) use ($uri) {
            try {
                return json_decode(json: $result, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new JsonException(
                    $e->getMessage() . ' (while fetching ' . $uri . ')',
                    1768312795,
                    new RuntimeException($result, 1768311544)
                );
            }
        });
    }

    protected function getRequestHeaders(string | UriInterface $uri): array
    {
        if ($uri instanceof UriInterface) {
            $uri = $uri->__toString();
        }

        $headers = [];
        foreach ($this->headers as $uriPattern => $headersForUriPattern) {
            if (!preg_match($uriPattern, $uri)) {
                continue;
            }
            foreach ($headersForUriPattern as $key => $value) {
                $headers[$key] = $value;
            }
        }

        $headers = array_merge($headers, $this->additionalHeaders);

        return array_map(fn ($header) => (string) $header, $headers);
    }

    /**
     * @param Uri $uri
     * @param array<string, string> $headers
     * @return PromiseInterface<string>
     */
    protected function fetch(Uri $uri, array $headers = []): PromiseInterface
    {
        return $this
            ->createClient()
            ->getAsync($uri, [
                'headers' => $headers,
            ])
            ->then(function (ResponseInterface $response) {
                return $response->getBody()->getContents();
            })
            ->otherwise(function (Throwable $exception) {
                if ($exception instanceof GuzzleServerException) {
                    throw ServerException::wrapException($exception->getRequest(), $exception);
                }
                if ($exception instanceof GuzzleClientException) {
                    throw ClientException::wrapException($exception->getRequest(), $exception);
                }
                throw $exception;
            });
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
            $cacheIdentifier = $this->calculateCacheIdentifierForTypeAndId($typeName, $id);
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
        $cacheIdentifier = $this->calculateCacheIdentifierForTypeAndId($type, $id);
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
    protected function calculateCacheIdentifierForTypeAndId($type, $id)
    {
        return $type . "\n" . $id;
    }



    protected function getCacheIdentifierForUri(Uri $uri): string | false
    {
        $uriString = (string) $uri;

        $headers = $this->getRequestHeaders($uriString);

        $headersForCacheIdentifier = [];
        foreach ($headers as $key => $value) {
            $key = strtolower($key);
            if ($key === 'cache-control' && strtolower($value) === 'no-store') {
                return false;
            }
            $headersForCacheIdentifier[] = sprintf('%s: %s', $key, $value);
        }
        return md5(serialize($headersForCacheIdentifier) . '|' . $uriString);
    }

    protected function normalizeType(string|Type $type): Type
    {
        if ($type instanceof Type) {
            return $type;
        }
        if (is_string($type)) {
            $typeName = $type;
            $type = $this->getType(typeName: $typeName);
        }

        if (is_null($type)) {
            throw new InvalidArgumentException('No json api type found for "' . $typeName . '"', 1763057463);
        }
        return $type;
    }

    public function createClient(): Client
    {
        return $this->clientProvider->createClient();
    }
}
