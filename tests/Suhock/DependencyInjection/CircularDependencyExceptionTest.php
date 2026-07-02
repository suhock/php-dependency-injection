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
 * Test suite for {@see CircularDependencyException}.
 */
class CircularDependencyExceptionTest extends TestCase
{
    private const TEST_CLASS = FakeClassNoConstructor::class;

    /**
     * @return CircularDependencyException<FakeClassNoConstructor>
     */
    private function createException(): CircularDependencyException
    {
        return new CircularDependencyException(self::TEST_CLASS);
    }

    public function testGetMessage_HasClassName_ContainsClassName(): void
    {
        // Arrange
        $exception = $this->createException();

        // Act
        $message = $exception->getMessage();

        // Assert
        self::assertStringContainsString(self::TEST_CLASS, $message);
    }

    public function testGetClassName_HasClassName_ReturnsClassName(): void
    {
        // Arrange
        $exception = $this->createException();

        // Act
        $className = $exception->getClassName();

        // Assert
        self::assertSame(self::TEST_CLASS, $className);
    }
}
