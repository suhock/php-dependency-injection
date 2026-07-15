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
use Suhock\DependencyInjection\ScopeException;

/**
 * Test suite for {@see ScopedStrategy}.
 */
final class ScopedStrategyTest extends AbstractDependencyInjectionTestCase
{
    /**
     * @return ScopedStrategy<FakeClassNoConstructor>
     */
    protected function createStrategy(): ScopedStrategy
    {
        return new ScopedStrategy(FakeClassNoConstructor::class);
    }

    public function testGet_WithRootContext_ThrowsScopeException(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $context = self::createResolutionContext();

        // Act & Assert
        $this->expectException(ScopeException::class);
        $strategy->get($context, fn() => new FakeClassNoConstructor());
    }

    public function testGet_WhenCalledMultipleTimesInScope_ReturnsSameInstance(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $scopeContext = self::createScopeResolutionContext(self::createResolutionContext());

        // Act
        $firstInstance = $strategy->get($scopeContext, fn() => new FakeClassNoConstructor());
        $secondInstance = $strategy->get($scopeContext, fn() => new FakeClassNoConstructor());

        // Assert
        self::assertSame($firstInstance, $secondInstance);
    }

    public function testGet_WithDistinctScopeContexts_ReturnsDistinctInstances(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $rootContext = self::createResolutionContext();
        $firstScopeContext = self::createScopeResolutionContext($rootContext);
        $secondScopeContext = self::createScopeResolutionContext($rootContext);

        // Act
        $firstInstance = $strategy->get($firstScopeContext, fn() => new FakeClassNoConstructor());
        $secondInstance = $strategy->get($secondScopeContext, fn() => new FakeClassNoConstructor());

        // Assert
        self::assertNotSame($firstInstance, $secondInstance);
    }

    public function testGet_WithScopeContext_InvokesFactoryWithScopeContext(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $scopeContext = self::createScopeResolutionContext(self::createResolutionContext());

        // Act
        $strategy->get($scopeContext, function (ResolutionContext $factoryContext) use ($scopeContext) {
            // Assert
            self::assertSame($scopeContext, $factoryContext);

            return new FakeClassNoConstructor();
        });
    }
}
