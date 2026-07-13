<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\InstanceProvider;

use Suhock\DependencyInjection\AbstractDependencyInjectionTestCase;
use Suhock\DependencyInjection\Fakes\FakeBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\ResolutionContext;

/**
 * Test suite for {@see ContextInstanceProvider}.
 */
final class ContextInstanceProviderTest extends AbstractDependencyInjectionTestCase
{
    public function testGet_WhenSelectorReturnsConformingInstance_ReturnsSelectorValue(): void
    {
        // Arrange
        $expectedInstance = new FakeClassExtendsBaseClass();
        $provider = new ContextInstanceProvider(
            FakeBaseClass::class,
            static fn (ResolutionContext $context) => $expectedInstance
        );

        // Act
        $instance = $provider->get(self::createResolutionContext());

        // Assert
        self::assertSame($expectedInstance, $instance);
    }

    public function testGet_WhenSelectorReturnsNonConformingInstance_ThrowsInstanceTypeException(): void
    {
        // Arrange
        $provider = new ContextInstanceProvider(
            FakeClassExtendsBaseClass::class,
            static fn (ResolutionContext $context) => new FakeClassNoConstructor()
        );

        $context = self::createResolutionContext();

        // Act
        $fn = static fn () => $provider->get($context);

        // Assert
        self::assertThrowsInstanceTypeException(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
            $fn
        );
    }

    public function testGetDependencySource_ReturnsLeafSource(): void
    {
        // Arrange
        $provider = new ContextInstanceProvider(
            FakeBaseClass::class,
            static fn (ResolutionContext $context) => new FakeBaseClass()
        );

        // Act
        $source = $provider->getDependencySource();

        // Assert
        self::assertInstanceOf(LeafSource::class, $source);
    }

    public function testGet_PassesGivenResolutionContextToSelector(): void
    {
        // Arrange
        $expectedContext = self::createResolutionContext();
        $receivedContext = null;
        $provider = new ContextInstanceProvider(
            FakeBaseClass::class,
            static function (ResolutionContext $context) use (&$receivedContext) {
                $receivedContext = $context;

                return new FakeBaseClass();
            }
        );

        // Act
        $provider->get($expectedContext);

        // Assert
        self::assertSame($expectedContext, $receivedContext);
    }
}
