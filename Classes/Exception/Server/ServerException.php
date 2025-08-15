<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Exception\Server;

use GuzzleHttp\Exception\ServerException as GuzzleServerException;
use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * @Flow\Proxy(false))
 */
abstract class ServerException extends GuzzleServerException
{
    public static function wrapException(RequestInterface $request, Throwable $e): GuzzleServerException
    {
        $exception = parent::wrapException($request, $e);
        return match ($exception->getResponse()->getStatusCode()) {
            BadGateway::STATUS_CODE => new BadGateway(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception->getPrevious(),
                $exception->getHandlerContext()
            ),
            ServiceUnavailable::STATUS_CODE => new ServiceUnavailable(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception->getPrevious(),
                $exception->getHandlerContext()
            ),
            GatewayTimeout::STATUS_CODE => new GatewayTimeout(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception->getPrevious(),
                $exception->getHandlerContext()
            ),
            InternalServerError::STATUS_CODE => new InternalServerError(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception->getPrevious(),
                $exception->getHandlerContext()
            ),
            default => $exception
        };
    }
}