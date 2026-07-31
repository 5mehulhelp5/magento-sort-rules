<?php
/**
 * Copyright © StackNuts. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace StackNuts\SortRules\Test\Unit\Model\Config\Backend;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use StackNuts\SortRules\Model\Config\Backend\RuleSerializer;

/**
 * RuleSerializer's ArraySerialized base needs a full Context/Registry/ScopeConfig/TypeList
 * constructor that beforeSave()'s own logic never touches, so the instance is built via
 * reflection with just the two inherited properties it actually reads: the event manager and
 * the JSON serializer.
 */
class RuleSerializerTest extends TestCase
{
    private function buildSerializer(): RuleSerializer
    {
        /** @var RuleSerializer $serializer */
        $serializer = (new \ReflectionClass(RuleSerializer::class))->newInstanceWithoutConstructor();

        $this->setInheritedProperty($serializer, \Magento\Framework\Model\AbstractModel::class, '_eventManager', $this->createStub(ManagerInterface::class));
        $this->setInheritedProperty($serializer, \Magento\Config\Model\Config\Backend\Serialized::class, 'serializer', new Json());

        return $serializer;
    }

    private function setInheritedProperty(object $object, string $declaringClass, string $property, mixed $value): void
    {
        (new \ReflectionProperty($declaringClass, $property))->setValue($object, $value);
    }

    private function savedRows(RuleSerializer $serializer): array
    {
        return (array) (new Json())->unserialize($serializer->getValue());
    }

    public function testDropsRowsMissingCodeOrAttribute(): void
    {
        $serializer = $this->buildSerializer();
        $serializer->setValue([
            '_1000000000000_001' => ['code' => 'newest', 'target_attribute' => 'created_at'],
            '_1000000000000_002' => ['code' => '', 'target_attribute' => 'price'],
            '_1000000000000_003' => ['code' => 'cheapest', 'target_attribute' => ''],
        ]);

        $serializer->beforeSave();

        $rows = $this->savedRows($serializer);
        $this->assertCount(1, $rows);
        $this->assertSame('newest', $rows['_1000000000000_001']['code']);
    }

    public function testPreservesRowKeysThroughSaveInsteadOfCollapsingToSequentialIndices(): void
    {
        // The grid's own JS template uses each row's key directly as a CSS id selector
        // (tr#<id>) for delete-button binding, and the stored config value is a JSON object
        // keyed by them, not a plain JSON array - losing the original "_<timestamp>_<n>" keys
        // (e.g. via array_values()/usort() collapsing to 0, 1, 2...) breaks both.
        $serializer = $this->buildSerializer();
        $serializer->setValue([
            '_1750864870172_172' => ['code' => 'newest', 'target_attribute' => 'created_at', 'sort_order' => '5'],
            '_1750864870999_500' => ['code' => 'cheapest', 'target_attribute' => 'price', 'sort_order' => '1'],
        ]);

        $serializer->beforeSave();

        $rows = $this->savedRows($serializer);
        // Sorted by sort_order (cheapest=1 before newest=5), but each row keeps its own key.
        $this->assertSame(['_1750864870999_500', '_1750864870172_172'], array_keys($rows));
        $this->assertSame('newest', $rows['_1750864870172_172']['code']);
        $this->assertSame('cheapest', $rows['_1750864870999_500']['code']);
    }

    public function testRenumbersSortOrderSequentiallyByExistingOrder(): void
    {
        $serializer = $this->buildSerializer();
        $serializer->setValue([
            '_row_b' => ['code' => 'b', 'target_attribute' => 'price', 'sort_order' => '10'],
            '_row_a' => ['code' => 'a', 'target_attribute' => 'name', 'sort_order' => '2'],
        ]);

        $serializer->beforeSave();

        $rows = $this->savedRows($serializer);
        $this->assertSame('a', $rows['_row_a']['code']);
        $this->assertSame(1, $rows['_row_a']['sort_order']);
        $this->assertSame('b', $rows['_row_b']['code']);
        $this->assertSame(2, $rows['_row_b']['sort_order']);
    }

    public function testRowsWithoutSortOrderSortLast(): void
    {
        $serializer = $this->buildSerializer();
        $serializer->setValue([
            '_row_1' => ['code' => 'no_order', 'target_attribute' => 'price'],
            '_row_2' => ['code' => 'ordered', 'target_attribute' => 'name', 'sort_order' => '1'],
        ]);

        $serializer->beforeSave();

        $rows = $this->savedRows($serializer);
        $this->assertSame(['ordered', 'no_order'], array_column($rows, 'code'));
    }

    public function testNonArrayValueIsLeftAlone(): void
    {
        $serializer = $this->buildSerializer();
        $serializer->setValue(null);

        $serializer->beforeSave();

        $this->assertNull($serializer->getValue());
    }

    public function testRejectsDuplicateCodesAmongEnabledRows(): void
    {
        $serializer = $this->buildSerializer();
        $serializer->setValue([
            '_row_1' => ['code' => 'price_desc', 'target_attribute' => 'price', 'is_enabled' => '1'],
            '_row_2' => ['code' => 'price_desc', 'target_attribute' => 'special_price', 'is_enabled' => '1'],
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Sort Rules: the code "price_desc" is used by more than one enabled rule. '
            . 'Each enabled rule needs a unique code.'
        );

        $serializer->beforeSave();
    }

    public function testAllowsDuplicateCodeWhenOnlyOneRowIsEnabled(): void
    {
        $serializer = $this->buildSerializer();
        $serializer->setValue([
            '_row_1' => ['code' => 'price_desc', 'target_attribute' => 'price', 'is_enabled' => '1'],
            '_row_2' => ['code' => 'price_desc', 'target_attribute' => 'special_price', 'is_enabled' => '0'],
        ]);

        $serializer->beforeSave();

        $rows = $this->savedRows($serializer);
        $this->assertCount(2, $rows);
    }

    public function testAllowsSameCodeReusedAcrossSeparateSaves(): void
    {
        // Unique codes overall shouldn't ever trip the check.
        $serializer = $this->buildSerializer();
        $serializer->setValue([
            '_row_1' => ['code' => 'price_desc', 'target_attribute' => 'price', 'is_enabled' => '1'],
            '_row_2' => ['code' => 'name_asc', 'target_attribute' => 'name', 'is_enabled' => '1'],
        ]);

        $serializer->beforeSave();

        $rows = $this->savedRows($serializer);
        $this->assertCount(2, $rows);
    }
}
