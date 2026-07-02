<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Exception;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see DependencyInjectionException}.
 */
class AbstractDependencyInjectionExceptionTest extends TestCase
{
    public function testConstruct_WithMessage_ConstructsWithMessage(): void
    {
        // Arrange & Act
        $exception = new ContainerException('Message1');

        // Assert
        self::assertSame('Message1', $exception->getMessage());
    }

    public function testConstruct_WithPreviousDependencyInjectionException_BuildsCompositeMessage(): void
    {
        // Arrange
        $originalException = new ContainerException('Message1');

        // Act
        $exception = new ContainerException('Message2', $originalException);

        // Assert
        self::assertMatchesRegularExpression('/Message2.*Message1/s', $exception->getMessage());
        self::assertNull($exception->getPrevious());
        self::assertSame($originalException, $exception->getConsolidatedException());
    }

    public function testConstruct_WithPreviousDependencyInjectionException_DoesNotPopulatePreviousException(): void
    {
        // Arrange
        $originalException = new ContainerException('Message1');

        // Act
        $exception = new ContainerException('Message2', $originalException);

        // Assert
        self::assertNull($exception->getPrevious());
    }

    public function testConstruct_WithPreviousDependencyInjectionException_PopulatesConsolidatedException(): void
    {
        // Arrange
        $originalException = new ContainerException('Message1');

        // Act
        $exception = new ContainerException('Message2', $originalException);

        // Assert
        self::assertSame($originalException, $exception->getConsolidatedException());
    }

    public function testConstruct_WithPreviousUnrelatedException_ConstructsWithMessage(): void
    {
        // Arrange & Act
        $exception = new ContainerException('Message2', new Exception('Message1'));

        // Assert
        self::assertSame('Message2', $exception->getMessage());
    }

    public function testConstruct_WithPreviousUnrelatedException_PopulatesPreviousException(): void
    {
        // Arrange
        $originalException = new Exception('Message1');

        // Act
        $exception = new ContainerException('Message2', $originalException);

        // Assert
        self::assertSame($originalException, $exception->getPrevious());
    }

    public function testConstruct_WithPreviousUnrelatedException_DoesNotPopulateConsolidatedException(): void
    {
        // Arrange & Act
        $exception = new ContainerException('Message2', new Exception('Message1'));

        // Assert
        self::assertNull($exception->getConsolidatedException());
    }
}
