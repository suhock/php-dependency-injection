<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use PHPUnit\Framework\TestCase;
use ReflectionParameter;

/**
 * Test suite for {@see ParameterResolutionException}.
 */
class ParameterResolutionExceptionTest extends TestCase
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
        $exception = $this->createException();

        self::assertStringContainsString('fakeFunction', $exception->getMessage());
    }

    public function testGetMessage_HasParameterName_ContainsParameterName(): void
    {
        $exception = $this->createException();

        self::assertStringContainsString('fakeParameter', $exception->getMessage());
    }

    public function testGetMessage_HasParameterType_ContainsParameterType(): void
    {
        $exception = $this->createException();

        self::assertStringContainsString('string', $exception->getMessage());
    }

    public function testGetReflectionParameter_ReturnsReflectionParameter(): void
    {
        $exception = $this->createException();

        self::assertInstanceOf(ReflectionParameter::class, $exception->getReflectionParameter());
    }
}
