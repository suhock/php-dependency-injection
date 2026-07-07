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
 * Test suite for {@see SingletonStrategy}.
 */
final class SingletonStrategyTest extends TestCase
{
    /**
     * @return SingletonStrategy<FakeClassNoConstructor>
     */
    protected function createStrategy(): SingletonStrategy
    {
        return new SingletonStrategy(FakeClassNoConstructor::class);
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

    public function testGet_WhenCalledMultipleTimes_ReturnsSameInstance(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $context = new ResolutionContext(new InstanceStore());

        // Act
        $firstInstance = $strategy->get($context, fn () => new FakeClassNoConstructor());
        $secondInstance = $strategy->get($context, fn () => new FakeClassNoConstructor());

        // Assert
        self::assertSame($firstInstance, $secondInstance);
    }

    public function testGet_WithDistinctRootStores_ReturnsDistinctInstances(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $firstContext = new ResolutionContext(new InstanceStore());
        $secondContext = new ResolutionContext(new InstanceStore());

        // Act
        $firstInstance = $strategy->get($firstContext, fn () => new FakeClassNoConstructor());
        $secondInstance = $strategy->get($secondContext, fn () => new FakeClassNoConstructor());

        // Assert
        self::assertNotSame($firstInstance, $secondInstance);
    }

    public function testGet_AfterInstanceRemovedFromStore_ReturnsFreshInstance(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $store = new InstanceStore();
        $context = new ResolutionContext($store);
        $firstInstance = $strategy->get($context, fn () => new FakeClassNoConstructor());

        // Act
        $store->remove($strategy);
        $secondInstance = $strategy->get($context, fn () => new FakeClassNoConstructor());

        // Assert
        self::assertNotSame($firstInstance, $secondInstance);
    }
}
