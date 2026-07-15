<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Lifetime;

use Suhock\DependencyInjection\AbstractDependencyInjectionTestCase;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\ResolutionContext;

/**
 * Test suite for {@see SingletonStrategy}.
 */
final class SingletonStrategyTest extends AbstractDependencyInjectionTestCase
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
        $context = self::createResolutionContext();

        // Act
        $instance = $strategy->get($context, fn() => new FakeClassNoConstructor());

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
    }

    public function testGet_WhenCalledMultipleTimes_ReturnsSameInstance(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $context = self::createResolutionContext();

        // Act
        $firstInstance = $strategy->get($context, fn() => new FakeClassNoConstructor());
        $secondInstance = $strategy->get($context, fn() => new FakeClassNoConstructor());

        // Assert
        self::assertSame($firstInstance, $secondInstance);
    }

    public function testGet_WithDistinctRootContexts_ReturnsDistinctInstances(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $firstContext = self::createResolutionContext();
        $secondContext = self::createResolutionContext();

        // Act
        $firstInstance = $strategy->get($firstContext, fn() => new FakeClassNoConstructor());
        $secondInstance = $strategy->get($secondContext, fn() => new FakeClassNoConstructor());

        // Assert
        self::assertNotSame($firstInstance, $secondInstance);
    }

    public function testGet_WithScopeContext_SharesInstanceWithRootContext(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $rootContext = self::createResolutionContext();
        $scopeContext = self::createScopeResolutionContext($rootContext);

        // Act
        $scopeInstance = $strategy->get($scopeContext, fn() => new FakeClassNoConstructor());
        $rootInstance = $strategy->get($rootContext, fn() => new FakeClassNoConstructor());

        // Assert
        self::assertSame($scopeInstance, $rootInstance);
    }

    public function testGet_WithScopeContext_InvokesFactoryWithRootContext(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $rootContext = self::createResolutionContext();
        $scopeContext = self::createScopeResolutionContext($rootContext);

        // Act
        $strategy->get($scopeContext, function (ResolutionContext $factoryContext) use ($rootContext) {
            // Assert
            self::assertSame($rootContext, $factoryContext);

            return new FakeClassNoConstructor();
        });
    }
}
