<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Context;

use PHPUnit\Framework\TestCase;
use Suhock\DependencyInjection\Fakes\FakeIntBackedEnum;
use Suhock\DependencyInjection\Fakes\FakeStringBackedEnum;
use Suhock\DependencyInjection\Fakes\FakeUnitEnum;

/**
 * Test suite for {@see Context} class.
 */
class ContextTest extends TestCase
{
    public function testGetName_WithString_ReturnsStringValue(): void
    {
        // Arrange
        $context = new Context('test');

        // Act
        $name = $context->getName();

        // Assert
        self::assertSame('test', $name);
    }

    public function testGetName_WithUnitEnum_ReturnsNameOfEnumValue(): void
    {
        // Arrange
        $context = new Context(FakeUnitEnum::Test);

        // Act
        $name = $context->getName();

        // Assert
        self::assertSame('Test', $name);
    }

    public function testGetName_WithStringBackedEnum_ReturnsStringBackingEnumValue(): void
    {
        // Arrange
        $context = new Context(FakeStringBackedEnum::Test);

        // Act
        $name = $context->getName();

        // Assert
        self::assertSame('test', $name);
    }

    public function testGetName_WithIntBackedEnum_ReturnsNameOfEnumValue(): void
    {
        // Arrange
        $context = new Context(FakeIntBackedEnum::Test);

        // Act
        $name = $context->getName();

        // Assert
        self::assertSame('Test', $name);
    }
}
