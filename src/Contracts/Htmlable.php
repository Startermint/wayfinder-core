<?php

declare(strict_types=1);

namespace Wayfinder\Contracts;

interface Htmlable
{
    public function toHtml(): string;
}
