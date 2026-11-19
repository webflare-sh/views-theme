<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Service;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Webflare\ViewsTheme\Struct\VariantsGrid;
use Webflare\ViewsTheme\Struct\VariantsGridPagination;

class VariantsGridLoader
{
    public const PAGE_PARAMETER = 'variantsPage';
    public const OPTIONS_PARAMETER = 'variantsOptions';

    private const CONFIG_ROWS_PER_PAGE = 'ViewsTheme.config.variantsGridRowsPerPage';
    private const DEFAULT_ROWS_PER_PAGE = 10;

    public function __construct(
        private readonly VariantsLoader $variantsLoader,
        private readonly SystemConfigService $systemConfig,
    ) {
    }

    public function load(string $parentId, Request $request, SalesChannelContext $context): ?VariantsGrid
    {
        $groups = $this->variantsLoader->loadOptionGroupsByParentId($parentId, $context);
        $optionIdsByGroup = $this->getSelectedOptionIdsByGroup($request, $groups);
        $total = $this->variantsLoader->countVariantsByParentId($parentId, $context, $optionIdsByGroup);

        if ($total === 0 && $optionIdsByGroup === []) {
            return null;
        }

        $limit = $this->getRowsPerPage($context);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min(max(1, $request->query->getInt(self::PAGE_PARAMETER, 1)), $totalPages);

        $variants = $total > 0
            ? $this->variantsLoader->loadVariantsPageByParentId(
                $parentId,
                ($page - 1) * $limit,
                $limit,
                $context,
                $optionIdsByGroup,
            )
            : new SalesChannelProductCollection();

        return new VariantsGrid(
            $variants,
            $groups,
            new VariantsGridPagination($page, $limit, $total),
            $parentId,
            array_merge([], ...$optionIdsByGroup),
            self::PAGE_PARAMETER,
            self::OPTIONS_PARAMETER,
        );
    }

    /**
     * @return list<list<string>>
     */
    private function getSelectedOptionIdsByGroup(Request $request, PropertyGroupCollection $groups): array
    {
        $selected = array_filter(explode('|', $request->query->getString(self::OPTIONS_PARAMETER)));

        if ($selected === []) {
            return [];
        }

        $optionIdsByGroup = [];

        foreach ($groups as $group) {
            $optionIds = array_values(array_intersect($group->getOptions()?->getIds() ?? [], $selected));

            if ($optionIds !== []) {
                $optionIdsByGroup[] = $optionIds;
            }
        }

        return $optionIdsByGroup;
    }

    private function getRowsPerPage(SalesChannelContext $context): int
    {
        $limit = (int) $this->systemConfig->get(self::CONFIG_ROWS_PER_PAGE, $context->getSalesChannelId());

        return $limit < 1 ? self::DEFAULT_ROWS_PER_PAGE : $limit;
    }
}
