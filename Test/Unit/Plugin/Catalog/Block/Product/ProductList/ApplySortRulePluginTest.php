<?php
/**
 * Copyright © StackNuts. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace StackNuts\SortRules\Test\Unit\Plugin\Catalog\Block\Product\ProductList;

use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Catalog\Model\Product\ProductList\Toolbar as ToolbarModel;
use PHPUnit\Framework\TestCase;
use StackNuts\SortRules\Model\Config;
use StackNuts\SortRules\Model\Rule;
use StackNuts\SortRules\Model\SortRuleResolver;
use StackNuts\SortRules\Plugin\Catalog\Block\Product\ProductList\ApplySortRulePlugin;

class ApplySortRulePluginTest extends TestCase
{
    private function rule(string $code, string $attribute, string $direction = 'asc', bool $force = false): Rule
    {
        return Rule::fromConfigRow([
            'code' => $code,
            'target_attribute' => $attribute,
            'direction' => $direction,
            'force_direction' => $force ? '1' : '0',
        ]);
    }

    private function buildConfig(bool $enabled = true): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);

        return $config;
    }

    private function buildPlugin(
        array $rulesByCode,
        string $requestOrder,
        $requestDirection,
        bool $enabled = true
    ): ApplySortRulePlugin {
        $resolver = $this->createStub(SortRuleResolver::class);
        $resolver->method('findByCode')->willReturnCallback(
            static fn (string $code) => $rulesByCode[$code] ?? null
        );

        $toolbarModel = $this->createStub(ToolbarModel::class);
        $toolbarModel->method('getOrder')->willReturn($requestOrder);
        $toolbarModel->method('getDirection')->willReturn($requestDirection);

        return new ApplySortRulePlugin($resolver, $toolbarModel, $this->buildConfig($enabled));
    }

    /**
     * A bare instance is enough: aroundSetCollection only touches the block's own setData()/
     * getData() (real DataObject methods, no dependencies) and the $proceed closure supplied by
     * the test - it never calls any of Toolbar's own methods that would need real collaborators.
     */
    private function bareToolbar(): Toolbar
    {
        return (new \ReflectionClass(Toolbar::class))->newInstanceWithoutConstructor();
    }

    public function testAfterGetAvailableOrdersLeavesNativeOrdersUntouchedWhenNoRulesMatch(): void
    {
        $resolver = $this->createStub(SortRuleResolver::class);
        $resolver->method('getToolbarOptions')->willReturn([]);

        $plugin = new ApplySortRulePlugin($resolver, $this->createStub(ToolbarModel::class), $this->buildConfig());

        $orders = $plugin->afterGetAvailableOrders($this->createStub(Toolbar::class), ['price' => 'Price']);

        $this->assertSame(['price' => 'Price'], $orders);
    }

    public function testAfterGetAvailableOrdersReplacesTargetedAttributeWithRuleOptions(): void
    {
        $resolver = $this->createMock(SortRuleResolver::class);
        $resolver->method('getToolbarOptions')->willReturn(['price_desc' => 'Price: High to Low']);
        $resolver->method('findByCode')->with('price_desc')->willReturn($this->rule('price_desc', 'price', 'desc'));

        $plugin = new ApplySortRulePlugin($resolver, $this->createStub(ToolbarModel::class), $this->buildConfig());

        $orders = $plugin->afterGetAvailableOrders(
            $this->createStub(Toolbar::class),
            ['position' => 'Position', 'price' => 'Price', 'name' => 'Product Name']
        );

        // "price" (native) is gone - replaced by the rule - while unrelated attributes survive.
        $this->assertSame(
            ['position' => 'Position', 'name' => 'Product Name', 'price_desc' => 'Price: High to Low'],
            $orders
        );
    }

    public function testAfterGetAvailableOrdersKeepsBothDirectionsWhenBothHaveRules(): void
    {
        $resolver = $this->createStub(SortRuleResolver::class);
        $resolver->method('getToolbarOptions')->willReturn([
            'price_asc' => 'Price: Low to High',
            'price_desc' => 'Price: High to Low',
        ]);
        $resolver->method('findByCode')->willReturnMap([
            ['price_asc', $this->rule('price_asc', 'price', 'asc')],
            ['price_desc', $this->rule('price_desc', 'price', 'desc')],
        ]);

        $plugin = new ApplySortRulePlugin($resolver, $this->createStub(ToolbarModel::class), $this->buildConfig());

        $orders = $plugin->afterGetAvailableOrders($this->createStub(Toolbar::class), ['price' => 'Price']);

        $this->assertSame(['price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low'], $orders);
    }

    public function testAfterGetAvailableOrdersLeavesNativeOrdersUntouchedWhenDisabled(): void
    {
        $resolver = $this->createMock(SortRuleResolver::class);
        $resolver->expects($this->never())->method('getToolbarOptions');

        $plugin = new ApplySortRulePlugin($resolver, $this->createStub(ToolbarModel::class), $this->buildConfig(false));

        $orders = $plugin->afterGetAvailableOrders($this->createStub(Toolbar::class), ['price' => 'Price']);

        $this->assertSame(['price' => 'Price'], $orders);
    }

    public function testAroundSetCollectionPresentsTheRealAttributeOnlyDuringProceed(): void
    {
        $plugin = $this->buildPlugin(['price_desc' => $this->rule('price_desc', 'price', 'desc')], 'price_desc', 'desc');
        $toolbar = $this->bareToolbar();

        $observedDuringProceed = null;
        $proceed = function ($collection) use ($toolbar, &$observedDuringProceed) {
            $observedDuringProceed = $toolbar->getData('_current_grid_order');

            return $toolbar;
        };

        $result = $plugin->aroundSetCollection($toolbar, $proceed, new \stdClass());

        $this->assertSame('price', $observedDuringProceed);
        $this->assertSame('price_desc', $toolbar->getData('_current_grid_order'));
        $this->assertSame($toolbar, $result);
    }

    public function testAroundSetCollectionPassesThroughUnchangedWhenNoRuleMatches(): void
    {
        $plugin = $this->buildPlugin([], 'name', 'asc');
        $toolbar = $this->bareToolbar();

        $proceedCalledWith = null;
        $proceed = function ($collection) use ($toolbar, &$proceedCalledWith) {
            $proceedCalledWith = $collection;

            return $toolbar;
        };
        $collection = new \stdClass();

        $result = $plugin->aroundSetCollection($toolbar, $proceed, $collection);

        $this->assertSame($collection, $proceedCalledWith);
        $this->assertNull($toolbar->getData('_current_grid_order'));
        $this->assertSame($toolbar, $result);
    }

    public function testAroundSetCollectionPassesThroughUnchangedWhenDisabled(): void
    {
        $plugin = $this->buildPlugin(
            ['price_desc' => $this->rule('price_desc', 'price', 'desc')],
            'price_desc',
            'desc',
            enabled: false
        );
        $toolbar = $this->bareToolbar();

        $proceed = fn ($collection) => $toolbar;

        $plugin->aroundSetCollection($toolbar, $proceed, new \stdClass());

        $this->assertNull($toolbar->getData('_current_grid_order'));
    }

    public function testAfterGetCurrentDirectionUsesRuleDirectionWhenNoUrlDirectionRequested(): void
    {
        $plugin = $this->buildPlugin(
            ['newest' => $this->rule('newest', 'created_at', 'desc')],
            'newest',
            false
        );

        $direction = $plugin->afterGetCurrentDirection($this->createStub(Toolbar::class), 'asc');

        $this->assertSame('desc', $direction);
    }

    public function testAfterGetCurrentDirectionRespectsUrlDirectionWhenNotForced(): void
    {
        $plugin = $this->buildPlugin(
            ['newest' => $this->rule('newest', 'created_at', 'desc', force: false)],
            'newest',
            'asc'
        );

        $direction = $plugin->afterGetCurrentDirection($this->createStub(Toolbar::class), 'asc');

        $this->assertSame('asc', $direction);
    }

    public function testAfterGetCurrentDirectionOverridesUrlDirectionWhenForced(): void
    {
        $plugin = $this->buildPlugin(
            ['newest' => $this->rule('newest', 'created_at', 'desc', force: true)],
            'newest',
            'asc'
        );

        $direction = $plugin->afterGetCurrentDirection($this->createStub(Toolbar::class), 'asc');

        $this->assertSame('desc', $direction);
    }

    public function testAfterGetCurrentDirectionReturnsNativeDirectionWhenNoRuleMatches(): void
    {
        $plugin = $this->buildPlugin([], 'name', 'asc');

        $direction = $plugin->afterGetCurrentDirection($this->createStub(Toolbar::class), 'asc');

        $this->assertSame('asc', $direction);
    }

    public function testAfterGetCurrentDirectionReturnsNativeDirectionWhenDisabled(): void
    {
        $plugin = $this->buildPlugin(
            ['newest' => $this->rule('newest', 'created_at', 'desc', force: true)],
            'newest',
            'asc',
            enabled: false
        );

        $direction = $plugin->afterGetCurrentDirection($this->createStub(Toolbar::class), 'asc');

        $this->assertSame('asc', $direction);
    }

    public function testAfterGetCurrentDirectionLookupUsesRawRequestOrderNotAnyTranslatedValue(): void
    {
        // Confirms the lookup is keyed by the rule's own code (from the toolbar model's raw
        // request order), not by whatever the block's own getCurrentOrder() might report -
        // aroundSetCollection only presents the translated attribute transiently, but this
        // shouldn't depend on that timing at all.
        $rule = $this->rule('price_desc', 'price', 'desc', force: true);
        $plugin = $this->buildPlugin(['price_desc' => $rule], 'price_desc', 'asc');

        $toolbar = $this->createStub(Toolbar::class);
        $toolbar->method('getCurrentOrder')->willReturn('price');

        $direction = $plugin->afterGetCurrentDirection($toolbar, 'asc');

        $this->assertSame('desc', $direction);
    }
}
