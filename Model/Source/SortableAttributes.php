<?php

declare(strict_types=1);

namespace StackNuts\SortRules\Model\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Lists every product attribute flagged "Used for Sorting in Product
 * Listing", so the Sort Rules grid can only ever offer attributes that are
 * actually safe to sort by, instead of a hard-coded list.
 *
 * "position" is added manually alongside them: it isn't a real EAV attribute, but Magento
 * treats it as a first-class sort option everywhere else (Toolbar::setCollection() special-cases
 * it via addAttributeToSort() rather than setOrder()), so a rule can safely target it too.
 */
class SortableAttributes implements OptionSourceInterface
{
    private ?array $options = null;

    public function __construct(
        private readonly CollectionFactory $attributeCollectionFactory
    ) {
    }

    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $collection = $this->attributeCollectionFactory->create();
        $collection->addFieldToFilter('used_for_sort_by', 1);
        $collection->addOrder('attribute_code', 'ASC');

        $options = [
            ['value' => '', 'label' => __('-- Select an attribute --')],
            ['value' => 'position', 'label' => __('Position')],
        ];

        foreach ($collection as $attribute) {
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => sprintf('%s (%s)', $attribute->getFrontendLabel(), $attribute->getAttributeCode()),
            ];
        }

        return $this->options = $options;
    }
}
