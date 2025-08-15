<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Exception\Client;

use GuzzleHttp\Exception\ClientException as GuzzleClientException;
use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * @Flow\Proxy(false))
 */
abstract class ClientException extends GuzzleClientException
{
    public static function wrapException(RequestInterface $request, Throwable $e): GuzzleClientException
    {
        $exception = parent::wrapException($request, $e);
        return match ($exception->getResponse()->getStatusCode()) {
            BadRequest::STATUS_CODE => new BadRequest(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception->getPrevious(),
                $exception->getHandlerContext()
            ),
            Unauthorized::STATUS_CODE => new Unauthorized(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception->getPrevious(),
                $exception->getHandlerContext()
            ),
            Forbidden::STATUS_CODE => new Forbidden(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception->getPrevious(),
                $exception->getHandlerContext()
            ),
            NotFound::STATUS_CODE => new NotFound(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception->getPrevious(),
                $exception->getHandlerContext()
            ),
            MethodNotAllowed::STATUS_CODE => new MethodNotAllowed(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception->getPrevious(),
                $exception->getHandlerContext()
            ),
            NotAcceptable::STATUS_CODE => new NotAcceptable(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception->getResponse(),
                $exception->getPrevious(),
                $exception->getHandlerContext()
            ),
            RequestTimeout::STATUS_CODE => new RequestTimeout(
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