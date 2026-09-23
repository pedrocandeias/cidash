<?php

namespace App\Monitoring;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http as HttpClient;

/**
 * HTTP client for monitoring: identified user agent, short timeouts.
 */
class Http
{
    public static function client(): PendingRequest
    {
        return HttpClient::withUserAgent('CIDASH/'.config('app.version', '1').' (monitoring; '.config('app.url').')')
            ->timeout(20)
            ->connectTimeout(10)
            ->retry(2, 1000, throw: false);
    }
}
