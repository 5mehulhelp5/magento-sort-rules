<?php

declare(strict_types=1);

namespace StackNuts\SortRules\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use StackNuts\SortRules\Model\Rule;

/**
 * Reads the "Sort Rules" admin grid and turns it into a list of enabled
 * Rule objects, ordered the way the admin arranged them.
 */
class RuleReader
{
    private const CONFIG_PATH = 'stacknuts_sortrules/general/rules';

    /**
     * @var array<int|string, Rule[]>
     */
    private array $cache = [];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json
    ) {
    }

    /**
     * @return Rule[] enabled rules, keyed by code
     */
    public function getRules(?int $storeId = null): array
    {
        $cacheKey = $storeId ?? 'default';
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $rows = $this->decodeRows($storeId);

        $rules = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['is_enabled'])) {
                continue;
            }

            $rule = Rule::fromConfigRow($row);
            if ($rule->getCode() === '' || $rule->getAttribute() === '') {
                continue;
            }

            $rules[$rule->getCode()] = $rule;
        }

        uasort($rules, static fn (Rule $a, Rule $b) => $a->getSortOrder() <=> $b->getSortOrder());

        return $this->cache[$cacheKey] = $rules;
    }

    private function decodeRows(?int $storeId): array
    {
        $raw = $this->scopeConfig->getValue(self::CONFIG_PATH, ScopeInterface::SCOPE_STORE, $storeId);

        if (is_array($raw)) {
            return $raw;
        }

        if (empty($raw)) {
            return [];
        }

        try {
            return (array) $this->json->unserialize($raw);
        } catch (\InvalidArgumentException) {
            return [];
        }
    }
}
