<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use PHPUnit\Framework\TestCase;
use ReflectionParameter;

/**
 * Test suite for {@see ParameterResolutionException}.
 */
final class ParameterResolutionExceptionTest extends TestCase
{
    private function fakeFunction(string $fakeParameter): void
    {
    }

    private function createException(): ParameterResolutionException
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return new ParameterResolutionException(new ReflectionParameter($this->fakeFunction(...), 'fakeParameter'));
    }

    public function testGetMessage_HasFunctionName_ContainsFunctionName(): void
    {
        // Arrange
        $exception = $this->createException();

        // Act
        $message = $exception->getMessage();

        // Assert
        self::assertStringContainsString('fakeFunction', $message);
    }

    public function testGetMessage_HasParameterName_ContainsParameterName(): void
    {
        // Arrange
        $exception = $this->createException();

        // Act
        $message = $exception->getMessage();

        // Assert
        self::assertStringContainsString('fakeParameter', $message);
    }

    public function testGetMessage_HasParameterType_ContainsParameterType(): void
    {
        // Arrange
        $exception = $this->createException();

        // Act
        $message = $exception->getMessage();

        // Assert
        self::assertStringContainsString('string', $message);
    }

    public function testGetReflectionParameter_ReturnsReflectionParameter(): void
    {
        // Arrange
        $exception = $this->createException();

        // Act
        $reflectionParameter = $exception->getReflectionParameter();

        // Assert
        self::assertInstanceOf(ReflectionParameter::class, $reflectionParameter);
    }
}
