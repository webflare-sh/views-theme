<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Struct;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Struct\Struct;

class VariantsGrid extends Struct
{
    /**
     * @param list<string> $selectedOptionIds
     */
    public function __construct(
        protected readonly SalesChannelProductCollection $variants,
        protected readonly PropertyGroupCollection $groups,
        protected readonly VariantsGridPagination $pagination,
        protected readonly string $parentId,
        protected readonly array $selectedOptionIds,
        protected readonly string $pageParameter,
        protected readonly string $optionsParameter,
    ) {
    }

    public function getVariants(): SalesChannelProductCollection
    {
        return $this->variants;
    }

    public function getGroups(): PropertyGroupCollection
    {
        return $this->groups;
    }

    public function getPagination(): VariantsGridPagination
    {
        return $this->pagination;
    }

    public function getParentId(): string
    {
        return $this->parentId;
    }

    /**
     * @return list<string>
     */
    public function getSelectedOptionIds(): array
    {
        return $this->selectedOptionIds;
    }

    public function getPageParameter(): string
    {
        return $this->pageParameter;
    }

    public function getOptionsParameter(): string
    {
        return $this->optionsParameter;
    }
}
