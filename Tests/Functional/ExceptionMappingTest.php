<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Tests\Functional;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Netlogix\JsonApiOrg\Consumer\Exception;
use Netlogix\JsonApiOrg\Consumer\Tests\Common\Guzzle\TestingClientProvider;

class ExceptionMappingTest extends FunctionalTestCase
{
    /**
     * @test
     * @dataProvider provideClientException
     * @dataProvider provideServerException
     */
    public function HTTP_response_error_status_codes_result_in_individual_exception_classes(
        int $statusCode,
        string $exceptionClassName
    ) {
        $uri = new Uri('https://127.0.0.1');

        $restingClientProvider = $this->objectManager->get(TestingClientProvider::class);
        $restingClientProvider->queueResponse(
            $uri,
            new Response($statusCode)
        );

        $this->expectException($exceptionClassName);

        $this->consumerBackend
            ->fetchFromUri($uri);
    }

    public static function provideClientException()
    {
        yield Exception\Client\BadRequest::class => [
            'statusCode' => Exception\Client\BadRequest::STATUS_CODE,
            'exceptionClassName' => Exception\Client\BadRequest::class,
        ];

        yield Exception\Client\Forbidden::class => [
            'statusCode' => Exception\Client\Forbidden::STATUS_CODE,
            'exceptionClassName' => Exception\Client\Forbidden::class,
        ];

        yield Exception\Client\MethodNotAllowed::class => [
            'statusCode' => Exception\Client\MethodNotAllowed::STATUS_CODE,
            'exceptionClassName' => Exception\Client\MethodNotAllowed::class,
        ];

        yield Exception\Client\NotAcceptable::class => [
            'statusCode' => Exception\Client\NotAcceptable::STATUS_CODE,
            'exceptionClassName' => Exception\Client\NotAcceptable::class,
        ];

        yield Exception\Client\NotFound::class => [
            'statusCode' => Exception\Client\NotFound::STATUS_CODE,
            'exceptionClassName' => Exception\Client\NotFound::class,
        ];

        yield Exception\Client\RequestTimeout::class => [
            'statusCode' => Exception\Client\RequestTimeout::STATUS_CODE,
            'exceptionClassName' => Exception\Client\RequestTimeout::class,
        ];

        yield Exception\Client\Unauthorized::class => [
            'statusCode' => Exception\Client\Unauthorized::STATUS_CODE,
            'exceptionClassName' => Exception\Client\Unauthorized::class,
        ];
    }

    public static function provideServerException()
    {
        yield Exception\Server\InternalServerError::class => [
            'statusCode' => Exception\Server\InternalServerError::STATUS_CODE,
            'exceptionClassName' => Exception\Server\InternalServerError::class,
        ];

        yield Exception\Server\BadGateway::class => [
            'statusCode' => Exception\Server\BadGateway::STATUS_CODE,
            'exceptionClassName' => Exception\Server\BadGateway::class,
        ];

        yield Exception\Server\GatewayTimeout::class => [
            'statusCode' => Exception\Server\GatewayTimeout::STATUS_CODE,
            'exceptionClassName' => Exception\Server\GatewayTimeout::class,
        ];

        yield Exception\Server\ServiceUnavailable::class => [
            'statusCode' => Exception\Server\ServiceUnavailable::STATUS_CODE,
            'exceptionClassName' => Exception\Server\ServiceUnavailable::class,
        ];
    }
}
