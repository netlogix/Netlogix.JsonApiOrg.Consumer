<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Exception\Server;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false))
 */
final class GatewayTimeout extends ServerException
{
    public const STATUS_CODE = 504;
}