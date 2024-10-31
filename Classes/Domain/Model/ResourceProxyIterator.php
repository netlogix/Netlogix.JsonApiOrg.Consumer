<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Domain\Model;

use Generator;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Utility\Now;
use Psr\Http\Message\UriInterface;

class ResourceProxyIterator implements \IteratorAggregate, \Countable
{
    /**
     * @var VariableFrontend
     */
    protected $cache;

    /**
     * @var Now
     */
    protected $now;

    /**
     * @var array<ResourceProxy>
     */
    protected $data = [];

    /**
     * @var array|null
     */
    protected $jsonResult = null;

    /**
     * @var string
     */
    protected $eTag = '';

    /**
     * @var string
     */
    protected $uri;

    protected function __construct(string $uri = '')
    {
        $this->uri = $uri;
    }

    public static function fromUri(UriInterface $uri): self
    {
        return new static((string)$uri);
    }

    public function injectCacheManager(CacheManager $cacheManager)
    {
        $this->cache = $cacheManager->getCache('NetlogixJsonApiOrgConsumer_ResultsCache');
    }

    public function injectNow(Now $now)
    {
        $this->now = $now;
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
     * @return Generator
     */
    public function getIterator(): Generator
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
    public function count(): int
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
        sort($tags);
        $absoluteLifetime = $lifetime === 0 ? 0 : $this->now->getTimestamp() + $lifetime;
        $eTag = sha1($absoluteLifetime . join($tags));

        if ($this->eTag === $eTag) {
            return $this;
        }
        $this->eTag = $eTag;
        $cacheData = [
            'jsonResult' => $this->jsonResult,
            'eTag' => $eTag,
        ];

        $this->cache->set($identifier, $cacheData, $tags, $lifetime);
        return $this;
    }

    public function loadFromCache(): self
    {
        $identifier = sha1($this->uri);
        $cacheData = $this->cache->get($identifier);
        if (is_array($cacheData)) {
            $this->jsonResult = $cacheData['jsonResult'] ?? null;
            $this->eTag = $cacheData['eTag'] ?? '';
        } else {
            $this->jsonResult = null;
            $this->eTag = '';
        }
        return $this;
    }

    protected function setRawJson(array $jsonResult): self
    {
        $this->jsonResult = $jsonResult;
        $this->eTag = '';
        $this->data = [];
        return $this;
    }
}