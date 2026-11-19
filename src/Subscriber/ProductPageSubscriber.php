<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Subscriber;

use Webflare\ViewsTheme\Service\VariantsGridLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductPageSubscriber implements EventSubscriberInterface
{
    private const CONFIG_VARIANTS_GRID_ACTIVE = 'ViewsTheme.config.variantsGridActive';

    public function __construct(
        private readonly VariantsGridLoader $variantsGridLoader,
        private readonly SystemConfigService $systemConfig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $product = $page->getProduct();
        $salesChannelContext = $event->getSalesChannelContext();

        $active = (bool) $this->systemConfig->get(
            self::CONFIG_VARIANTS_GRID_ACTIVE,
            $salesChannelContext->getSalesChannelId(),
        );

        if (!$active) {
            return;
        }

        $parentId = $product->getParentId() ?? $product->getId();
        $hasVariants = $product->getChildCount() > 0 || $product->getParentId() !== null;

        if (!$hasVariants) {
            return;
        }

        $variantsGrid = $this->variantsGridLoader->load($parentId, $event->getRequest(), $salesChannelContext);

        if ($variantsGrid === null) {
            return;
        }

        $viewsTheme = $page->getExtension('viewsTheme');
        if (!$viewsTheme instanceof ArrayStruct) {
            $viewsTheme = new ArrayStruct();
            $page->addExtension('viewsTheme', $viewsTheme);
        }

        $viewsTheme->set('variantsGrid', $variantsGrid);
    }
}
