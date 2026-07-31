<?php
/**
 * Copyright © StackNuts. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace StackNuts\SortRules\Test\Unit\Model\Source;

use Magento\Catalog\Model\Entity\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use PHPUnit\Framework\TestCase;
use StackNuts\SortRules\Model\Source\SortableAttributes;

class SortableAttributesTest extends TestCase
{
    /**
     * getFrontendLabel() is a magic DataObject getter, not a real declared method, so it can't be
     * configured on a mock via method(). A bare instance with real data serves the same purpose.
     */
    private function stubAttribute(string $code, string $label): Attribute
    {
        $attribute = (new \ReflectionClass(Attribute::class))->newInstanceWithoutConstructor();
        $attribute->setData(['attribute_code' => $code, 'frontend_label' => $label]);

        return $attribute;
    }

    public function testToOptionArrayFiltersToSortableAttributesAndPrependsPlaceholder(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('addFieldToFilter')->with('used_for_sort_by', 1);
        $collection->expects($this->once())->method('addOrder')->with('attribute_code', 'ASC');
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            $this->stubAttribute('price', 'Price'),
        ]));

        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $options = (new SortableAttributes($factory))->toOptionArray();

        $this->assertSame('', $options[0]['value']);
        $this->assertSame('position', $options[1]['value']);
        $this->assertSame('Position', (string) $options[1]['label']);
        $this->assertSame(['value' => 'price', 'label' => 'Price (price)'], $options[2]);
        $this->assertCount(3, $options);
    }

    public function testToOptionArrayResultIsCachedAcrossCalls(): void
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));

        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($collection);

        $source = new SortableAttributes($factory);
        $source->toOptionArray();
        $source->toOptionArray();
    }
}
