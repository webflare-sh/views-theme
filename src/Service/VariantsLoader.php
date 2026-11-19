<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Service;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class VariantsLoader
{
    /**
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     * @param EntityRepository<PropertyGroupOptionCollection> $optionRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly EntityRepository $optionRepository,
    ) {
    }

    /**
     * @param list<list<string>> $optionIdsByGroup
     */
    public function countVariantsByParentId(
        string $parentId,
        SalesChannelContext $context,
        array $optionIdsByGroup = [],
    ): int {
        $criteria = $this->createParentCriteria($parentId, $optionIdsByGroup)
            ->setLimit(1)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $criteria->setTitle('views-theme::variants-grid-count-variants');

        $result = $this->productRepository->search($criteria, $context);

        return $result->getTotal();
    }

    /**
     * @param list<list<string>> $optionIdsByGroup
     */
    public function loadVariantsPageByParentId(
        string $parentId,
        int $offset,
        int $limit,
        SalesChannelContext $context,
        array $optionIdsByGroup = [],
    ): SalesChannelProductCollection {
        $criteria = $this->createParentCriteria($parentId, $optionIdsByGroup)
            ->addAssociation('options.group')
            ->addAssociation('options.media')
            ->addAssociation('cover')
            ->addAssociation('calculatedPrices')
            ->addSorting(new FieldSorting('productNumber', FieldSorting::ASCENDING))
            ->setOffset($offset)
            ->setLimit($limit);

        $criteria->setTitle('views-theme::variants-grid-load-variants-page');

        $result = $this->productRepository->search($criteria, $context);

        /** @var SalesChannelProductCollection $variants */
        $variants = $result->getEntities();

        return $variants;
    }

    public function loadOptionGroupsByParentId(
        string $parentId,
        SalesChannelContext $context,
    ): PropertyGroupCollection {
        $criteria = $this->createParentCriteria($parentId, [])
            ->setLimit(1)
            ->addAggregation(new TermsAggregation('options', 'product.options.id'));

        $criteria->setTitle('views-theme::variants-grid-option-ids');

        $aggregation = $this->productRepository->aggregate($criteria, $context)->get('options');
        $optionIds = $aggregation instanceof TermsResult ? array_values(array_filter($aggregation->getKeys())) : [];

        if ($optionIds === []) {
            return new PropertyGroupCollection();
        }

        $optionCriteria = (new Criteria($optionIds))
            ->addAssociation('group')
            ->addAssociation('media');

        $optionCriteria->setTitle('views-theme::variants-grid-options');

        $groups = $this->optionRepository
            ->search($optionCriteria, $context->getContext())
            ->getEntities()
            ->groupByPropertyGroups();

        $groups->sortByPositions();
        $groups->sortByConfig($context->getLanguageInfo()->localeCode);

        return $groups;
    }

    /**
     * @param list<list<string>> $optionIdsByGroup
     */
    private function createParentCriteria(string $parentId, array $optionIdsByGroup): Criteria
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('parentId', $parentId));

        foreach ($optionIdsByGroup as $optionIds) {
            $criteria->addFilter(new EqualsAnyFilter('optionIds', $optionIds));
        }

        return $criteria;
    }
}
