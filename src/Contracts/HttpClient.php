<?php

declare(strict_types=1);

namespace Wayfinder\Contracts;

use Wayfinder\Http\Client\Request;
use Wayfinder\Http\Client\Response;

interface HttpClient
{
    public function send(Request $request): Response;
}
