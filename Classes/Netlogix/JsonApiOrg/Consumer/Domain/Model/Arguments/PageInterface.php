<?php

namespace Netlogix\JsonApiOrg\Consumer\Domain\Model\Arguments;

/*
 * This file is part of the Netlogix.JsonApiOrg.Consumer package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

interface PageInterface
{

    /**
     * @return array
     */
    function __toArray(): array;

}
