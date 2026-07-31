<?php

declare(strict_types=1);

namespace StackNuts\SortRules\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Config\Model\Config\Source\Yesno;
use StackNuts\SortRules\Model\Source\Direction;
use StackNuts\SortRules\Model\Source\SortableAttributes;

/**
 * Renders the "Sort Rules" admin grid: one row per storefront sort option.
 */
class RuleGrid extends AbstractFieldArray
{
    /**
     * @var array<string, SelectColumnRenderer>
     */
    private array $selectRenderers = [];

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        private readonly SortableAttributes $sortableAttributes,
        private readonly Direction $direction,
        private readonly Yesno $yesno,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _prepareToRender()
    {
        $this->addColumn('sort_order', ['label' => __('Display Order')]);
        $this->addColumn('code', ['label' => __('Sort Url Param')]);
        $this->addColumn('label', ['label' => __('Label')]);
        $this->addColumn('target_attribute', ['label' => __('Attribute'), 'renderer' => $this->getSelectRenderer('target_attribute', $this->sortableAttributes->toOptionArray())]);
        $this->addColumn('direction', ['label' => __('Default Direction'), 'renderer' => $this->getSelectRenderer('direction', $this->direction->toOptionArray())]);
        $this->addColumn('force_direction', ['label' => __('Force Direction'), 'renderer' => $this->getSelectRenderer('force_direction', $this->yesno->toOptionArray())]);
        $this->addColumn('is_enabled', ['label' => __('Enabled'), 'renderer' => $this->getSelectRenderer('is_enabled', $this->yesno->toOptionArray())]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Sort Rule');
    }

    /**
     * Restores each select's saved value as "selected". Every row renders through the same JS
     * template as brand new rows, so Magento's mechanism for this is is_render_to_js_template
     * plus an option_extra_attrs map keyed by calcOptionHash() (see Html\Select and
     * Magento\CatalogInventory\Block\Adminhtml\Form\Field\Minsaleqty) - each column needs its own
     * renderer instance since the hash depends on that renderer's own name/id.
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row): void
    {
        $optionExtraAttrs = [];
        foreach ($this->selectRenderers as $columnName => $renderer) {
            $hash = $renderer->calcOptionHash($row->getData($columnName));
            $optionExtraAttrs['option_' . $hash] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $optionExtraAttrs);
    }

    private function getSelectRenderer(string $columnName, array $options): SelectColumnRenderer
    {
        if (!isset($this->selectRenderers[$columnName])) {
            $renderer = $this->getLayout()->createBlock(
                SelectColumnRenderer::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $renderer->setOptions($options);
            $this->selectRenderers[$columnName] = $renderer;
        }

        return $this->selectRenderers[$columnName];
    }
}
