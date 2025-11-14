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

    /**
     * @param string $type
     * @param array $filter
     * @param array $include
     * @param PageInterface $page
     * @param SortInterface $sort
     * @return ResourceProxyIterator
     */
    public function findByTypeAndFilter($type, $filter = [], $include = [], PageInterface $page = null, SortInterface $sort = null);

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
}