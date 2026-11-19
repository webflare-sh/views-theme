<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Struct;

final readonly class CookieGroupView
{
    /**
     * @param list<CookieEntryView> $entries
     */
    public function __construct(
        public string $id,
        public string $name,
        public bool $required,
        public array $entries = [],
        public ?string $description = null,
        public ?string $cookie = null,
    ) {
    }
}
