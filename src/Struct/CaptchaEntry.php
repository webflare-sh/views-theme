<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Struct;

final readonly class CaptchaEntry
{
    /**
     * @param array<string, mixed> $props
     */
    public function __construct(
        public string $type,
        public string $component,
        public string $slot,
        public array $props = [],
        public bool $visible = true,
    ) {
    }
}
