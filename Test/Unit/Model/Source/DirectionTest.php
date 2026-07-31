<?php
/**
 * Copyright © StackNuts. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace StackNuts\SortRules\Test\Unit\Model\Source;

use PHPUnit\Framework\TestCase;
use StackNuts\SortRules\Model\Source\Direction;

class DirectionTest extends TestCase
{
    public function testToOptionArrayListsAscendingAndDescending(): void
    {
        $options = (new Direction())->toOptionArray();

        $this->assertSame(['asc', 'desc'], array_column($options, 'value'));
    }
}
