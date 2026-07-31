<?php
/**
 * Copyright © StackNuts. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace StackNuts\SortRules\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use StackNuts\SortRules\Model\Config\RuleReader;

class RuleReaderTest extends TestCase
{
    private function buildReader(ScopeConfigInterface $scopeConfig): RuleReader
    {
        return new RuleReader($scopeConfig, new Json());
    }

    public function testGetRulesEmptyWhenNotConfigured(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame([], $this->buildReader($scopeConfig)->getRules());
    }

    public function testGetRulesDecodesJsonAndKeysByCode(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(json_encode([
            [
                'code' => 'newest',
                'target_attribute' => 'created_at',
                'label' => 'Newest',
                'direction' => 'desc',
                'is_enabled' => '1',
                'sort_order' => '1',
            ],
        ]));

        $rules = $this->buildReader($scopeConfig)->getRules();

        $this->assertArrayHasKey('newest', $rules);
        $this->assertSame('created_at', $rules['newest']->getAttribute());
    }

    public function testGetRulesSkipsDisabledRows(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(json_encode([
            ['code' => 'a', 'target_attribute' => 'name', 'is_enabled' => '0'],
            ['code' => 'b', 'target_attribute' => 'price', 'is_enabled' => '1'],
        ]));

        $rules = $this->buildReader($scopeConfig)->getRules();

        $this->assertSame(['b'], array_keys($rules));
    }

    public function testGetRulesSkipsRowsMissingCodeOrAttribute(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(json_encode([
            ['code' => '', 'target_attribute' => 'name', 'is_enabled' => '1'],
            ['code' => 'c', 'target_attribute' => '', 'is_enabled' => '1'],
            ['code' => 'd', 'target_attribute' => 'price', 'is_enabled' => '1'],
        ]));

        $rules = $this->buildReader($scopeConfig)->getRules();

        $this->assertSame(['d'], array_keys($rules));
    }

    public function testGetRulesOrderedBySortOrder(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(json_encode([
            ['code' => 'second', 'target_attribute' => 'a', 'is_enabled' => '1', 'sort_order' => '2'],
            ['code' => 'first', 'target_attribute' => 'b', 'is_enabled' => '1', 'sort_order' => '1'],
        ]));

        $rules = $this->buildReader($scopeConfig)->getRules();

        $this->assertSame(['first', 'second'], array_keys($rules));
    }

    public function testGetRulesEmptyOnMalformedJson(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('not valid json');

        $this->assertSame([], $this->buildReader($scopeConfig)->getRules());
    }

    public function testGetRulesAcceptsAlreadyDecodedArrayValue(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn([
            ['code' => 'a', 'target_attribute' => 'name', 'is_enabled' => '1'],
        ]);

        $this->assertSame(['a'], array_keys($this->buildReader($scopeConfig)->getRules()));
    }

    public function testGetRulesResultIsCachedPerStore(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())->method('getValue')->willReturn(json_encode([
            ['code' => 'a', 'target_attribute' => 'name', 'is_enabled' => '1'],
        ]));

        $reader = $this->buildReader($scopeConfig);

        $reader->getRules(1);
        $reader->getRules(1);
    }
}
