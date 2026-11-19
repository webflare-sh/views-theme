<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Tests\Unit\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Webflare\ViewsTheme\Service\VariantsGridLoader;
use Webflare\ViewsTheme\Service\VariantsLoader;

final class VariantsGridLoaderTest extends TestCase
{
    private VariantsLoader&MockObject $variantsLoader;

    private VariantsGridLoader $loader;

    private SalesChannelContext&Stub $context;

    protected function setUp(): void
    {
        $this->variantsLoader = $this->createMock(VariantsLoader::class);
        $this->variantsLoader
            ->method('loadOptionGroupsByParentId')
            ->willReturn($this->createGroups(['color' => ['red', 'blue'], 'size' => ['s', 'm']]));

        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturn(10);

        $this->context = $this->createStub(SalesChannelContext::class);
        $this->context->method('getSalesChannelId')->willReturn('sales-channel');

        $this->loader = new VariantsGridLoader($this->variantsLoader, $config);
    }

    public function testGroupsSelectedOptionsPerGroupAndDropsUnknownIds(): void
    {
        $this->variantsLoader
            ->expects(self::once())
            ->method('countVariantsByParentId')
            ->with('parent', $this->context, [['red', 'blue'], ['m']])
            ->willReturn(3);
        $this->variantsLoader
            ->expects(self::once())
            ->method('loadVariantsPageByParentId')
            ->with('parent', 0, 10, $this->context, [['red', 'blue'], ['m']])
            ->willReturn(new SalesChannelProductCollection());

        $grid = $this->loader->load(
            'parent',
            new Request(['variantsOptions' => 'red|unknown|m|blue']),
            $this->context,
        );

        self::assertNotNull($grid);
        self::assertSame(['red', 'blue', 'm'], $grid->getSelectedOptionIds());
        self::assertSame(3, $grid->getPagination()->getTotal());
        self::assertSame('variantsOptions', $grid->getOptionsParameter());
    }

    public function testReturnsNullWithoutVariantsAndWithoutFilter(): void
    {
        $this->variantsLoader->method('countVariantsByParentId')->willReturn(0);
        $this->variantsLoader->expects(self::never())->method('loadVariantsPageByParentId');

        self::assertNull($this->loader->load('parent', new Request(), $this->context));
    }

    public function testKeepsGridWhenFilterMatchesNoVariant(): void
    {
        $this->variantsLoader->method('countVariantsByParentId')->willReturn(0);
        $this->variantsLoader->expects(self::never())->method('loadVariantsPageByParentId');

        $grid = $this->loader->load('parent', new Request(['variantsOptions' => 'red|s']), $this->context);

        self::assertNotNull($grid);
        self::assertCount(0, $grid->getVariants());
        self::assertSame(0, $grid->getPagination()->getTotal());
        self::assertSame(1, $grid->getPagination()->getPage());
        self::assertSame(['red', 's'], $grid->getSelectedOptionIds());
    }

    public function testClampsPageToLastPage(): void
    {
        $this->variantsLoader->method('countVariantsByParentId')->willReturn(14);
        $this->variantsLoader
            ->expects(self::once())
            ->method('loadVariantsPageByParentId')
            ->with('parent', 10, 10, $this->context, [])
            ->willReturn(new SalesChannelProductCollection());

        $grid = $this->loader->load('parent', new Request(['variantsPage' => '99']), $this->context);

        self::assertNotNull($grid);
        self::assertSame(2, $grid->getPagination()->getPage());
    }

    /**
     * @param array<string, list<string>> $optionIdsByGroup
     */
    private function createGroups(array $optionIdsByGroup): PropertyGroupCollection
    {
        $groups = new PropertyGroupCollection();

        foreach ($optionIdsByGroup as $groupId => $optionIds) {
            $options = new PropertyGroupOptionCollection();
            foreach ($optionIds as $optionId) {
                $option = new PropertyGroupOptionEntity();
                $option->setId($optionId);
                $option->setGroupId($groupId);
                $options->add($option);
            }

            $group = new PropertyGroupEntity();
            $group->setId($groupId);
            $group->setOptions($options);
            $groups->add($group);
        }

        return $groups;
    }
}
