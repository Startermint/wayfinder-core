<?php

declare(strict_types=1);

namespace Wayfinder\View;

use Illuminate\Contracts\Support\Htmlable as IlluminateHtmlable;
use Wayfinder\Contracts\Htmlable;

final class HtmlString implements Htmlable, IlluminateHtmlable
{
    public function __construct(
        private readonly string $html,
    ) {
    }

    public function toHtml(): string
    {
        return $this->html;
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
