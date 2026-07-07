<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Lifetime;

use PHPUnit\Framework\TestCase;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;

/**
 * Test suite for {@see TransientStrategy}.
 */
final class TransientStrategyTest extends TestCase
{
    /**
     * @return TransientStrategy<FakeClassNoConstructor>
     */
    protected function createStrategy(): TransientStrategy
    {
        return new TransientStrategy(FakeClassNoConstructor::class);
    }

    public function testGet_WithFactoryFunction_ReturnsValueFromFactoryFunction(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $context = new ResolutionContext(new InstanceStore());

        // Act
        $instance = $strategy->get($context, fn () => new FakeClassNoConstructor());

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
    }

    public function testGet_WhenCalledMultipleTimes_ReturnsDistinctInstances(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $context = new ResolutionContext(new InstanceStore());

        // Act
        $firstInstance = $strategy->get($context, fn () => new FakeClassNoConstructor());
        $secondInstance = $strategy->get($context, fn () => new FakeClassNoConstructor());

        // Assert
        self::assertNotSame($firstInstance, $secondInstance);
    }
}
