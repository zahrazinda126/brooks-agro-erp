<?php

namespace App\Support\Demo;

use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;

/** Sends requests through the application as a signed-in person (used by the demo-data commands). */
class HttpReplay
{
    use MakesHttpRequests, InteractsWithAuthentication, InteractsWithSession;

    public function __construct(protected $app)
    {
    }
}
