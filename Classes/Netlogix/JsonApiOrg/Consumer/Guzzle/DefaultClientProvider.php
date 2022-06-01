<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Guzzle;

use GuzzleHttp\Client;

final class DefaultClientProvider implements ClientProvider
{

    public function createClient(): Client
    {
        return new Client();
    }

}
