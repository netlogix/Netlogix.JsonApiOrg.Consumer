<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\Consumer\Guzzle;

use GuzzleHttp\Client;

interface ClientProvider
{

    public function createClient(): Client;

}
