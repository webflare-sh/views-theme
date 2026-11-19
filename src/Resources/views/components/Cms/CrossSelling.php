<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Resources\views\components\Cms;

use Webflare\ViewsTheme\Struct\CrossSellingTab;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElement;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent]
class CrossSelling
{
    /**
     * `CrossSellingElementCollection` (CMS element data) or any iterable of `CrossSellingElement`.
     */
    public mixed $crossSellings = null;

    public string $boxLayout = 'default';

    public string|int|null $itemWidth = null;

    public ?string $skipTarget = null;

    /**
     * @var array<string, mixed>
     */
    public array $cva = [];

    /**
     * @var list<CrossSellingTab>
     */
    public array $tabs = [];

    public ?string $activeTabId = null;

    public bool $visible = false;

    /**
     * @param array<string, mixed> $data
     */
    #[PostMount]
    public function postMount(array $data): void
    {
        $this->tabs = $this->buildTabs($this->crossSellings);
        $this->activeTabId = $this->tabs[0]->tabId ?? null;
        $this->visible = $this->tabs !== [];
    }

    /**
     * @return list<CrossSellingTab>
     */
    private function buildTabs(mixed $crossSellings): array
    {
        if (!is_iterable($crossSellings)) {
            return [];
        }

        $tabs = [];
        foreach ($crossSellings as $element) {
            if (!$element instanceof CrossSellingElement || $element->getTotal() <= 0) {
                continue;
            }

            $crossSelling = $element->getCrossSelling();
            if (!$crossSelling->isActive()) {
                continue;
            }

            $id = $crossSelling->getId();
            $tabs[] = new CrossSellingTab(
                id: $id,
                name: $this->translatedName($crossSelling),
                products: $element->getProducts(),
                tabId: 'cross-selling-tab-' . $id,
                panelId: 'cross-selling-tab-' . $id . '-pane',
            );
        }

        return $tabs;
    }

    private function translatedName(ProductCrossSellingEntity $crossSelling): string
    {
        $translated = $crossSelling->getTranslation('name');
        if (\is_string($translated) && $translated !== '') {
            return $translated;
        }

        return (string) ($crossSelling->getName() ?? '');
    }
}
