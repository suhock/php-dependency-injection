<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see UnresolvedParameterException}.
 */
class UnresolvedParameterExceptionTest extends TestCase
{
    public function testGetMessage_HasFunctionName_ContainsFunctionName(): void
    {
        $exception = new UnresolvedParameterException('testFunction', 'testParameter');

        self::assertStringContainsString('testFunction', $exception->getMessage());
    }

    public function testGetMessage_HasParameterName_ContainsParameterName(): void
    {
        $exception = new UnresolvedParameterException('testFunction', 'testParameter');

        self::assertStringContainsString('testParameter', $exception->getMessage());
    }

    public function testGetMessage_HasParameterType_ContainsParameterType(): void
    {
        $exception = new UnresolvedParameterException('testFunction', 'testParameter', 'string');

        self::assertStringContainsString('string', $exception->getMessage());
    }

    public function testGetFunctionName_HasFunctionName_ReturnsFunctionName(): void
    {
        $exception = new UnresolvedParameterException('testFunction', 'testParameter');

        self::assertSame('testFunction', $exception->getFunctionName());
    }

    public function testGetParameterName_HasParameterName_ReturnsParameterName(): void
    {
        $exception = new UnresolvedParameterException('testFunction', 'testParameter');

        self::assertSame('testParameter', $exception->getParameterName());
    }

    public function testGetParameterType_HasParameterType_ReturnsParameterType(): void
    {
        $exception = new UnresolvedParameterException('testFunction', 'testParameter', 'string');

        self::assertSame('string', $exception->getParameterType());
    }

    public function testGetParameterType_ParameterTypeIsNull_ReturnsNull(): void
    {
        $exception = new UnresolvedParameterException('testFunction', 'testParameter');

        self::assertNull($exception->getParameterType());
    }
}
