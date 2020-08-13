<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Domain\Model;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Http\Uri;

class ResourceProxyIterator implements \IteratorAggregate, \Countable
{
    /**
     * @var VariableFrontend
     */
    protected $cache;

    /**
     * @var array<ResourceProxy>
     */
    protected $data = [];

    /**
     * @var array
     */
    protected $jsonResult;

    /**
     * @var string
     */
    protected $uri;

    protected function __construct(string $uri = '')
    {
        $this->uri = $uri;
    }

    public static function fromUri(Uri $uri): self
    {
        return new static((string)$uri);
    }

    public function injectCacheManager(CacheManager $cacheManager)
    {
        $this->cache = $cacheManager->getCache('NetlogixJsonApiOrgConsumer_ResultsCache');
    }

    public function initialize(callable $convertResourceDefinitionToResourceProxy): self
    {
        $result = $this->jsonResult ?? [];

        $this->data = array_map($convertResourceDefinitionToResourceProxy, $result['data']);
        array_map($convertResourceDefinitionToResourceProxy, $result['included']);

        return $this;
    }

    public function withJsonResult(array $jsonResult): self
    {
        $jsonResult['data'] = $jsonResult['data'] ?? [];
        if (isset($jsonResult['data']['type']) && isset($jsonResult['data']['id'])) {
            $jsonResult['data'] = [$jsonResult['data']];
        }
        $jsonResult['included'] = $jsonResult['included'] ?? [];

        $new = new static($this->uri);
        $new->setRawJson($jsonResult);

        return $new;
    }

    /**
     * @return \Generator
     */
    public function getIterator()
    {
        yield from $this->data;
    }

    public function getArrayCopy(): array
    {
        return iterator_to_array($this, false);
    }

    /**
     * @return int
     */
    public function count()
    {
        if (isset($this->jsonResult)
            && array_key_exists('meta', $this->jsonResult)
            && array_key_exists('total', $this->jsonResult['meta'])) {

            return $this->jsonResult['meta']['total'];
        }

        return count($this->data);
    }

    public function getLinks()
    {
        return $this->jsonResult['links'] ?? [];
    }

    public function hasResults(): bool
    {
        return is_array($this->jsonResult);
    }

    public function saveToCache(int $lifetime, string ...$tags): self
    {
        $identifier = sha1($this->uri);
        $this->cache->set($identifier, $this->jsonResult, $tags, $lifetime);
        return $this;
    }

    public function loadFromCache(): self
    {
        $identifier = sha1($this->uri);
        $jsonResult = $this->cache->get($identifier);
        $this->jsonResult = $jsonResult !== false ? $jsonResult : null;
        return $this;
    }

    protected function setRawJson(array $jsonResult): self
    {
        $this->jsonResult = $jsonResult;
        $this->data = [];
        return $this;
    }
}