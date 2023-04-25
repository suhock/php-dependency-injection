<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
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
        $exception = new ContainerException('Message1');
        self::assertSame('Message1', $exception->getMessage());
    }

    public function testConstruct_WithPreviousDependencyInjectionException_BuildsCompositeMessage(): void
    {
        $originalException = new ContainerException('Message1');
        $exception = new ContainerException('Message2', $originalException);
        self::assertMatchesRegularExpression('/Message2.*Message1/s', $exception->getMessage());
        self::assertNull($exception->getPrevious());
        self::assertSame($originalException, $exception->getConsolidatedException());
    }

    public function testConstruct_WithPreviousDependencyInjectionException_DoesNotPopulatePreviousException(): void
    {
        $originalException = new ContainerException('Message1');
        $exception = new ContainerException('Message2', $originalException);
        self::assertNull($exception->getPrevious());
    }

    public function testConstruct_WithPreviousDependencyInjectionException_PopulatesConsolidatedException(): void
    {
        $originalException = new ContainerException('Message1');
        $exception = new ContainerException('Message2', $originalException);
        self::assertSame($originalException, $exception->getConsolidatedException());
    }

    public function testConstruct_WithPreviousUnrelatedException_ConstructsWithMessage(): void
    {
        $exception = new ContainerException('Message2', new Exception('Message1'));
        self::assertSame('Message2', $exception->getMessage());
    }

    public function testConstruct_WithPreviousUnrelatedException_PopulatesPreviousException(): void
    {
        $originalException = new Exception('Message1');
        $exception = new ContainerException('Message2', $originalException);
        self::assertSame($originalException, $exception->getPrevious());
    }

    public function testConstruct_WithPreviousUnrelatedException_DoesNotPopulateConsolidatedException(): void
    {
        $exception = new ContainerException('Message2', new Exception('Message1'));
        self::assertNull($exception->getConsolidatedException());
    }
}
