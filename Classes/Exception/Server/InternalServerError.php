<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Exception\Server;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false))
 */
final class InternalServerError extends ServerException
{
    public const STATUS_CODE = 500;
}