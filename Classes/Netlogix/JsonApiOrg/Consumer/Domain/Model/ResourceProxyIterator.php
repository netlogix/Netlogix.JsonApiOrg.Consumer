<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Domain\Model;

class ResourceProxyIterator implements \IteratorAggregate, \Countable
{

    /**
     * @var array<ResourceProxy>
     */
    protected $data;

    /**
     * @var array
     */
    protected $jsonResult;

    public function __construct(array $data, array $jsonResult = null)
    {
        $this->data = $data;
        if ($jsonResult !== null) {
            $this->jsonResult = $jsonResult;
        }
    }

    /**
     * @return \Generator
     */
    public function getIterator()
    {
        yield from $this->data;
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


}