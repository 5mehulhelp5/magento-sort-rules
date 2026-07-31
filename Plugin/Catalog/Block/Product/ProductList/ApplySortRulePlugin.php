<?php

declare(strict_types=1);

namespace StackNuts\SortRules\Plugin\Catalog\Block\Product\ProductList;

use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Catalog\Model\Product\ProductList\Toolbar as ToolbarModel;
use StackNuts\SortRules\Model\Config;
use StackNuts\SortRules\Model\SortRuleResolver;

/**
 * Makes the storefront toolbar honour admin-configured sort rules: maps a rule's "code" onto its
 * real attribute only for the moment Toolbar actually applies it to the product collection, and
 * forces the configured direction when the rule says so.
 */
class ApplySortRulePlugin
{
    public function __construct(
        private readonly SortRuleResolver $resolver,
        private readonly ToolbarModel $toolbarModel,
        private readonly Config $config
    ) {
    }

    /**
     * Registers rule codes as recognized order values (native validation would otherwise reject
     * them as unrecognized) and replaces a targeted attribute's plain entry with its rule(s), so
     * a rule offers one curated option instead of sitting alongside an attribute still reachable
     * through the native direction toggle.
     */
    public function afterGetAvailableOrders(Toolbar $subject, array $orders): array
    {
        if (!$this->config->isEnabled()) {
            return $orders;
        }

        $ruleOptions = $this->resolver->getToolbarOptions($orders);
        if ($ruleOptions === []) {
            return $orders;
        }

        $targetedAttributes = [];
        foreach (array_keys($ruleOptions) as $code) {
            $targetedAttributes[$this->resolver->findByCode($code)->getAttribute()] = true;
        }

        return array_diff_key($orders, $targetedAttributes) + $ruleOptions;
    }

    /**
     * Presents the rule's real attribute only for the duration of setCollection()'s own sort
     * logic (including its "position" special case), then restores the rule's own code - so the
     * collection is sorted correctly exactly once (critical on a search-engine-backed catalog,
     * where a leaked invalid sort key fails the whole request), while the storefront template's
     * "selected" option comparison still matches the rule's own value.
     */
    public function aroundSetCollection(Toolbar $subject, callable $proceed, $collection): Toolbar
    {
        if (!$this->config->isEnabled()) {
            return $proceed($collection);
        }

        $rawOrder = (string) $this->toolbarModel->getOrder();
        $rule = $this->resolver->findByCode($rawOrder);
        if ($rule === null) {
            return $proceed($collection);
        }

        $subject->setData('_current_grid_order', $rule->getAttribute());
        $result = $proceed($collection);
        $subject->setData('_current_grid_order', $rawOrder);

        return $result;
    }

    public function afterGetCurrentDirection(Toolbar $subject, string $direction): string
    {
        if (!$this->config->isEnabled()) {
            return $direction;
        }

        $rule = $this->resolver->findByCode((string) $this->toolbarModel->getOrder());
        if ($rule === null) {
            return $direction;
        }

        if (!$this->toolbarModel->getDirection() || $rule->isForceDirection()) {
            return $rule->getDirection();
        }

        return $direction;
    }
}
