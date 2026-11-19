<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Resources\views\components\Checkout\Success;

use Webflare\ViewsTheme\Resources\views\components\Order\Addresses as OrderAddresses;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Addresses extends OrderAddresses
{
}
