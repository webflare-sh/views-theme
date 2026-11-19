<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Struct;

use Shopware\Core\Content\Product\ProductCollection;

final readonly class CrossSellingTab
{
    public function __construct(
        public string $id,
        public string $name,
        public ProductCollection $products,
        public string $tabId,
        public string $panelId,
    ) {
    }
}
