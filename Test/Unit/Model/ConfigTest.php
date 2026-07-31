<?php
/**
 * Copyright © StackNuts. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace StackNuts\SortRules\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use StackNuts\SortRules\Model\Config;

class ConfigTest extends TestCase
{
    public function testIsEnabledReflectsConfiguredFlag(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);

        $this->assertTrue((new Config($scopeConfig))->isEnabled());
    }

    public function testIsEnabledFalseWhenTurnedOff(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(false);

        $this->assertFalse((new Config($scopeConfig))->isEnabled());
    }
}
