<?php
namespace Netlogix\JsonApiOrg\Consumer\Domain\Model\Arguments;

/*
 * This file is part of the Netlogix.JsonApiOrg.Consumer package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

class Sort implements SortInterface
{

    /**
     * @var array
     */
    protected $properties = [];

    public function __construct(array $properties)
    {
        $this->properties = $properties;
    }

    function __toString(): string
    {
        $sort = [];

        foreach ($this->properties as $property => $direction) {
            if ($direction === self::DIRECTION_DESC) {
                $sort[] = '-' . $property;
            } else {
                $sort[] = $property;
            }
        }

        return join(',', $sort);
    }

}