<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Exception\Client;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false))
 */
final class MethodNotAllowed extends ClientException
{
    public const STATUS_CODE = 405;
}