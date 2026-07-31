<?php
/**
 * Copyright © StackNuts. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace StackNuts\SortRules\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use StackNuts\SortRules\Model\Rule;

class RuleTest extends TestCase
{
    public function testFromConfigRowMapsAllFields(): void
    {
        $rule = Rule::fromConfigRow([
            'code' => 'newest',
            'target_attribute' => 'created_at',
            'label' => 'Newest',
            'direction' => 'desc',
            'force_direction' => '1',
            'sort_order' => '3',
        ]);

        $this->assertSame('newest', $rule->getCode());
        $this->assertSame('created_at', $rule->getAttribute());
        $this->assertSame('Newest', $rule->getLabel());
        $this->assertSame('desc', $rule->getDirection());
        $this->assertTrue($rule->isForceDirection());
        $this->assertSame(3, $rule->getSortOrder());
    }

    public function testFromConfigRowDefaultsMissingFields(): void
    {
        $rule = Rule::fromConfigRow([]);

        $this->assertSame('', $rule->getCode());
        $this->assertSame('', $rule->getAttribute());
        $this->assertSame('', $rule->getLabel());
        $this->assertSame('asc', $rule->getDirection());
        $this->assertFalse($rule->isForceDirection());
        $this->assertSame(0, $rule->getSortOrder());
    }

    public function testFromConfigRowNormalizesAnyNonDescDirectionToAsc(): void
    {
        $rule = Rule::fromConfigRow(['direction' => 'sideways']);

        $this->assertSame('asc', $rule->getDirection());
    }

    public function testFromConfigRowForceDirectionAcceptsTruthyStrings(): void
    {
        $this->assertTrue(Rule::fromConfigRow(['force_direction' => '1'])->isForceDirection());
        $this->assertFalse(Rule::fromConfigRow(['force_direction' => '0'])->isForceDirection());
        $this->assertFalse(Rule::fromConfigRow(['force_direction' => ''])->isForceDirection());
    }
}
