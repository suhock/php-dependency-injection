<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Context;

use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see Context} class.
 */
class ContextTest extends TestCase
{
    public function testGetName_WithString_ReturnsStringValue(): void
    {
        $context = new Context('test');
        self::assertSame('test', $context->getName());
    }

    public function testGetName_WithUnitEnum_ReturnsNameOfEnumValue(): void
    {
        $context = new Context(FakeUnitEnum::Test);
        self::assertSame('Test', $context->getName());
    }

    public function testGetName_WithStringBackedEnum_ReturnsStringBackingEnumValue(): void
    {
        $context = new Context(FakeStringBackedEnum::Test);
        self::assertSame('test', $context->getName());
    }

    public function testGetName_WithIntBackedEnum_ReturnsNameOfEnumValue(): void
    {
        $context = new Context(FakeIntBackedEnum::Test);
        self::assertSame('Test', $context->getName());
    }
}
