<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Suhock\DependencyInjection\Fakes\FakeIntBackedEnum;
use Suhock\DependencyInjection\Fakes\FakeStringBackedEnum;
use Suhock\DependencyInjection\Fakes\FakeUnitEnum;

/**
 * Test suite for {@see Key} class.
 */
final class KeyTest extends TestCase
{
    public function testGetKey_WithString_ReturnsStringValue(): void
    {
        // Arrange
        $key = new Key('test');

        // Act
        $value = $key->getKey();

        // Assert
        self::assertSame('test', $value);
    }

    public function testGetKey_WithUnitEnum_ReturnsNameOfEnumValue(): void
    {
        // Arrange
        $key = new Key(FakeUnitEnum::Test);

        // Act
        $value = $key->getKey();

        // Assert
        self::assertSame('Test', $value);
    }

    public function testGetKey_WithStringBackedEnum_ReturnsStringBackingEnumValue(): void
    {
        // Arrange
        $key = new Key(FakeStringBackedEnum::Test);

        // Act
        $value = $key->getKey();

        // Assert
        self::assertSame('test', $value);
    }

    public function testGetKey_WithIntBackedEnum_ReturnsNameOfEnumValue(): void
    {
        // Arrange
        $key = new Key(FakeIntBackedEnum::Test);

        // Act
        $value = $key->getKey();

        // Assert
        self::assertSame('Test', $value);
    }
}
