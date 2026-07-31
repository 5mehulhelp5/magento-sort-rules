<?php
/**
 * Copyright © StackNuts. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace StackNuts\SortRules\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use StackNuts\SortRules\Model\Config\RuleReader;
use StackNuts\SortRules\Model\Rule;
use StackNuts\SortRules\Model\SortRuleResolver;

class SortRuleResolverTest extends TestCase
{
    private function buildResolver(array $rules): SortRuleResolver
    {
        $reader = $this->createStub(RuleReader::class);
        $reader->method('getRules')->willReturn($rules);

        return new SortRuleResolver($reader);
    }

    private function rule(string $code, string $attribute, string $label = '', string $direction = 'asc'): Rule
    {
        return Rule::fromConfigRow([
            'code' => $code,
            'target_attribute' => $attribute,
            'label' => $label,
            'direction' => $direction,
        ]);
    }

    public function testFindByCodeReturnsMatchingRule(): void
    {
        $resolver = $this->buildResolver(['newest' => $this->rule('newest', 'created_at')]);

        $this->assertSame('created_at', $resolver->findByCode('newest')->getAttribute());
    }

    public function testFindByCodeReturnsNullWhenNoMatch(): void
    {
        $resolver = $this->buildResolver(['newest' => $this->rule('newest', 'created_at')]);

        $this->assertNull($resolver->findByCode('missing'));
    }

    public function testGetToolbarOptionsReturnsCodeToLabelMap(): void
    {
        $resolver = $this->buildResolver([
            'newest' => $this->rule('newest', 'created_at', 'Newest'),
            'price_low' => $this->rule('price_low', 'price', 'Price: Low to High'),
        ]);

        $this->assertSame(
            ['newest' => 'Newest', 'price_low' => 'Price: Low to High'],
            $resolver->getToolbarOptions()
        );
    }

    public function testGetToolbarOptionsFiltersToNativeAttributesWhenProvided(): void
    {
        $resolver = $this->buildResolver([
            'newest' => $this->rule('newest', 'created_at', 'Newest'),
            'price_low' => $this->rule('price_low', 'price', 'Price: Low to High'),
        ]);

        $options = $resolver->getToolbarOptions(['price' => 'Price']);

        $this->assertSame(['price_low' => 'Price: Low to High'], $options);
    }

    public function testResolveDefaultCodePrefersExactAttributeAndDirectionMatch(): void
    {
        $resolver = $this->buildResolver([
            'price_asc' => $this->rule('price_asc', 'price', 'Price Low-High', 'asc'),
            'price_desc' => $this->rule('price_desc', 'price', 'Price High-Low', 'desc'),
        ]);

        $code = $resolver->resolveDefaultCode(
            ['price_asc' => true, 'price_desc' => true],
            'price',
            'desc'
        );

        $this->assertSame('price_desc', $code);
    }

    public function testResolveDefaultCodeFallsBackToAttributeOnlyMatch(): void
    {
        $resolver = $this->buildResolver([
            'price_asc' => $this->rule('price_asc', 'price', 'Price Low-High', 'asc'),
        ]);

        $code = $resolver->resolveDefaultCode(['price_asc' => true], 'price', 'desc');

        $this->assertSame('price_asc', $code);
    }

    public function testResolveDefaultCodeReturnsFalseWhenMatchIsNotInAvailableCodes(): void
    {
        $resolver = $this->buildResolver([
            'price_asc' => $this->rule('price_asc', 'price', 'Price Low-High', 'asc'),
        ]);

        $code = $resolver->resolveDefaultCode(['something_else' => true], 'price', 'asc');

        $this->assertFalse($code);
    }

    public function testResolveDefaultCodeReturnsFalseWhenNoRuleMatchesAttribute(): void
    {
        $resolver = $this->buildResolver([
            'price_asc' => $this->rule('price_asc', 'price', 'Price Low-High', 'asc'),
        ]);

        $code = $resolver->resolveDefaultCode(['price_asc' => true], 'name', 'asc');

        $this->assertFalse($code);
    }
}
