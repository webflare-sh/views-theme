<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Webflare\ViewsTheme\Service\VariantsLoader;

final class VariantsLoaderTest extends TestCase
{
    public function testOptionFilterIsOrWithinAGroupAndAndAcrossGroups(): void
    {
        $captured = null;

        $productRepository = $this->createStub(SalesChannelRepository::class);
        $productRepository
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria) use (&$captured): EntitySearchResult {
                $captured = $criteria;

                return new EntitySearchResult('product', 4, new SalesChannelProductCollection(), null, $criteria, Context::createDefaultContext());
            });

        $loader = new VariantsLoader($productRepository, $this->createStub(EntityRepository::class));

        $total = $loader->countVariantsByParentId(
            'parent',
            $this->createStub(SalesChannelContext::class),
            [['red', 'blue'], ['m']],
        );

        self::assertSame(4, $total);
        self::assertInstanceOf(Criteria::class, $captured);

        $filters = $captured->getFilters();
        self::assertCount(3, $filters);
        self::assertEquals(new EqualsFilter('parentId', 'parent'), $filters[0]);
        self::assertEquals(new EqualsAnyFilter('optionIds', ['red', 'blue']), $filters[1]);
        self::assertEquals(new EqualsAnyFilter('optionIds', ['m']), $filters[2]);
    }
}
