<?php

namespace Netlogix\JsonApiOrg\Consumer\Service;

/*
 * This file is part of the Netlogix.JsonApiOrg.Consumer package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use GuzzleHttp\Promise\PromiseInterface;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\Arguments\PageInterface;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\Arguments\SortInterface;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\ResourceProxy;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\ResourceProxyIterator;
use Netlogix\JsonApiOrg\Consumer\Domain\Model\Type;
use Psr\Http\Message\UriInterface;

interface ConsumerBackendInterface
{
    /**
     * @param Type $type
     */
    public function addType(Type $type);

    /**
     * @param string $typeName
     * @return Type|null
     */
    public function getType($typeName);

    /**
     * @param UriInterface $endpointDiscovery
     */
    public function registerEndpointsByEndpointDiscovery(UriInterface $endpointDiscovery): PromiseInterface;

    public function findByTypeAndFilter(
        string | Type $type,
        array $filter = [],
        array $include = [],
        ?PageInterface $page = null,
        ?SortInterface $sort = null
    ): ResourceProxyIterator;

    /**
     * @return PromiseInterface<ResourceProxyIterator>
     */
    public function requestByTypeAndFilter(
        string | Type $type,
        array $filter = [],
        array $include = [],
        ?PageInterface $page = null,
        ?SortInterface $sort = null
    ): PromiseInterface;

    /**
     * Fetch data from the given URI synchronously.
     * The resulting ResourceProxyIterator is fully populated.
     *
     * @return ResourceProxyIterator
     */
    public function fetchFromUri(UriInterface $queryUri): ResourceProxyIterator;

    /**
     * Fetch data from the given URI asynchronously.
     * The request will be executed immediately.
     *
     * @return PromiseInterface<ResourceProxyIterator>
     */
    public function requestFromUri(UriInterface $queryUri): PromiseInterface;

    /**
     * @param mixed $type
     * @param mixed $id
     * @return ResourceProxy
     */
    public function fetchByTypeAndId($type, $id);

    /**
     * @template T
     * @param array<string, string[]> $fields
     * @param (callable(self): T) $do
     * @return T
     */
    public function withSparseFields(array $fields, callable $do): mixed;

    /**
     * @template T
     * @param array<string, string> $additionalHeaders
     * @param (callable(self): T) $do
     * @return T
     */
    public function withHeaders(array $additionalHeaders, callable $do): mixed;
}