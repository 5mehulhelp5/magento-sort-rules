<?php

declare(strict_types=1);

namespace StackNuts\SortRules\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Direction implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'asc', 'label' => __('Ascending')],
            ['value' => 'desc', 'label' => __('Descending')],
        ];
    }
}
