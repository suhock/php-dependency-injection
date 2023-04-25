<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use PHPUnit\Framework\TestCase;

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
        $exception = $this->createException();

        self::assertStringContainsString(self::TEST_CLASS, $exception->getMessage());
    }

    public function testGetClassName_HasClassName_ReturnsClassName(): void
    {
        $exception = $this->createException();

        self::assertSame(self::TEST_CLASS, $exception->getClassName());
    }
}
