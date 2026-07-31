<?php

declare(strict_types=1);

namespace StackNuts\SortRules\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Html\Select;

/**
 * AbstractFieldArray::renderCellTemplate() calls setInputName()/setInputId() on whatever renderer
 * is attached to a column - Html\Select itself only has setName()/setId(), so every select-rendered
 * column needs these mappings.
 */
class SelectColumnRenderer extends Select
{
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    public function setInputId($value)
    {
        return $this->setId($value);
    }
}
