<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Struct;

final readonly class CookieEntryView
{
    public function __construct(
        public string $id,
        public string $cookie,
        public string $name,
        public ?string $description = null,
    ) {
    }
}
