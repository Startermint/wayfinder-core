<?php

declare(strict_types=1);

namespace Wayfinder\Exceptions;

use Throwable;
use Wayfinder\Http\Request;
use Wayfinder\Http\Response;

interface ExceptionRenderer
{
    public function render(Throwable $throwable, Request $request): Response;
}
