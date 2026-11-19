<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Resources\views\components\Product;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Prices
{
    public mixed $product = null;

    public bool $showPrice = true;

    public bool $showTieredPrices = true;

    public bool $showTaxNote = true;

    /**
     * @var array<string, mixed>
     */
    public array $cva = [];
}
