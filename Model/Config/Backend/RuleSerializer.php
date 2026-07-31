<?php

declare(strict_types=1);

namespace StackNuts\SortRules\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Serialized\ArraySerialized;
use Magento\Framework\Exception\LocalizedException;

/**
 * Drops incomplete grid rows and renumbers "sort_order" sequentially on
 * save, so an admin can reorder rules just by editing that one field
 * without worrying about gaps or clashes.
 */
class RuleSerializer extends ArraySerialized
{
    public function beforeSave()
    {
        $value = $this->getValue();

        if (is_array($value)) {
            // Row keys (e.g. "_1750864870172_172") must survive: the grid's JS uses them as CSS
            // id selectors, and the config is stored as a JSON object keyed by them, not an array.
            $rows = array_filter(
                $value,
                static fn ($row) => is_array($row) && !empty($row['code']) && !empty($row['target_attribute'])
            );

            $this->validateNoDuplicateCodesAmongEnabledRows($rows);

            uasort($rows, static fn (array $a, array $b) => (int) ($a['sort_order'] ?? PHP_INT_MAX) <=> (int) ($b['sort_order'] ?? PHP_INT_MAX));

            $position = 1;
            foreach ($rows as &$row) {
                $row['sort_order'] = $position++;
            }
            unset($row);

            $this->setValue($rows);
        }

        return parent::beforeSave();
    }

    /**
     * RuleReader keys its rules by code, so two enabled rows sharing a code silently lose one of
     * them with no warning - a disabled row can safely share a code with an enabled one (e.g. an
     * admin keeping an old version around while it's off), since only enabled rows ever collide.
     *
     * @param array<string, array<string, mixed>> $rows
     * @throws LocalizedException
     */
    private function validateNoDuplicateCodesAmongEnabledRows(array $rows): void
    {
        $codes = [];
        foreach ($rows as $row) {
            if (empty($row['is_enabled'])) {
                continue;
            }

            $codes[] = (string) $row['code'];
        }

        $duplicates = array_unique(array_diff_assoc($codes, array_unique($codes)));
        if ($duplicates !== []) {
            throw new LocalizedException(__(
                'Sort Rules: the code "%1" is used by more than one enabled rule. Each enabled rule needs a unique code.',
                reset($duplicates)
            ));
        }
    }
}
