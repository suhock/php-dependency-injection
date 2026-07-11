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
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;

/**
 * Test suite for {@see ClassNotFoundException}.
 */
final class ClassNotFoundExceptionTest extends TestCase
{
    public function testGetMessage_HasClassName_ContainsClassName(): void
    {
        // Arrange
        $exception = new ClassNotFoundException(FakeClassNoConstructor::class);

        // Act
        $message = $exception->getMessage();

        // Assert
        self::assertStringContainsString(FakeClassNoConstructor::class, $message);
    }

    public function testGetClassName_HasClassName_ReturnsClassName(): void
    {
        // Arrange
        $exception =  new ClassNotFoundException(FakeClassNoConstructor::class);

        // Act
        $className = $exception->getClassName();

        // Assert
        self::assertSame(FakeClassNoConstructor::class, $className);
    }
}
