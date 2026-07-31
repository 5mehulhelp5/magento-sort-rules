<?php

declare(strict_types=1);

namespace StackNuts\SortRules\Model;

use StackNuts\SortRules\Model\Config\RuleReader;

/**
 * Matches the sort code coming from the storefront request against the
 * admin-configured rules, and works out which label/direction combination
 * should win on the toolbar.
 */
class SortRuleResolver
{
    public function __construct(
        private readonly RuleReader $ruleReader
    ) {
    }

    public function findByCode(string $code): ?Rule
    {
        return $this->ruleReader->getRules()[$code] ?? null;
    }

    /**
     * Builds the option list for the toolbar "Sort By" dropdown, restricted
     * to whatever the native toolbar already considers a valid attribute.
     *
     * @param array $nativeOptions attribute_code => label, as built by core Magento
     * @return array code => label
     */
    public function getToolbarOptions(array $nativeOptions = []): array
    {
        $options = [];
        foreach ($this->ruleReader->getRules() as $rule) {
            if ($nativeOptions && !array_key_exists($rule->getAttribute(), $nativeOptions)) {
                continue;
            }
            $options[$rule->getCode()] = $rule->getLabel();
        }

        return $options;
    }

    /**
     * Finds the rule code that best matches the attribute/direction pair
     * Magento has resolved as the current sort, so a custom toolbar
     * template can pre-select the matching dropdown option.
     *
     * @param array $availableCodes only codes present as keys here are considered
     * @return string|false
     */
    public function resolveDefaultCode(array $availableCodes, string $attribute, string $direction)
    {
        $exactMatch = false;
        $attributeMatch = false;

        foreach ($this->ruleReader->getRules() as $rule) {
            if ($rule->getAttribute() !== $attribute) {
                continue;
            }
            if ($rule->getDirection() === $direction) {
                $exactMatch = $rule->getCode();
                break;
            }
            $attributeMatch = $rule->getCode();
        }

        foreach ([$exactMatch, $attributeMatch] as $candidate) {
            if ($candidate && isset($availableCodes[$candidate])) {
                return $candidate;
            }
        }

        return false;
    }
}
