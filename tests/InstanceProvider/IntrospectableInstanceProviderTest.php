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

/**
 * Test suite covering {@see IntrospectableInstanceProviderInterface::getDependencySource()} for each built-in
 * {@see InstanceProviderInterface} implementation.
 */
final class IntrospectableInstanceProviderTest extends AbstractDependencyInjectionTestCase
{
    public function testGetDependencySource_OnClassInstanceProviderWithoutMutator_ReturnsAutowireClassSource(): void
    {
        // Arrange
        $provider = new ClassInstanceProvider(FakeClassNoConstructor::class);

        // Act
        $source = $provider->getDependencySource();

        // Assert
        self::assertInstanceOf(AutowireClassSource::class, $source);
        self::assertSame(FakeClassNoConstructor::class, $source->className);
        self::assertNull($source->mutator);
    }

    public function testGetDependencySource_OnClassInstanceProviderWithMutator_ReturnsAutowireClassSourceWithMutator(): void
    {
        // Arrange
        $mutator = function (FakeClassNoConstructor $obj): void {
            $obj->string = 'test';
        };
        $provider = new ClassInstanceProvider(FakeClassNoConstructor::class, $mutator);

        // Act
        $source = $provider->getDependencySource();

        // Assert
        self::assertInstanceOf(AutowireClassSource::class, $source);
        self::assertSame(FakeClassNoConstructor::class, $source->className);
        self::assertSame($mutator, $source->mutator);
    }

    public function testGetDependencySource_OnClosureInstanceProvider_ReturnsCallableSource(): void
    {
        // Arrange
        $factory = static fn () => new FakeClassNoConstructor();
        $provider = new ClosureInstanceProvider(FakeClassNoConstructor::class, $factory);

        // Act
        $source = $provider->getDependencySource();

        // Assert
        self::assertInstanceOf(CallableSource::class, $source);
        self::assertSame($factory, $source->callable);
        self::assertSame(0, $source->skipLeadingParams);
        self::assertSame(FakeClassNoConstructor::class, $source->declaredType);
    }

    public function testGetDependencySource_OnImplementationInstanceProvider_ReturnsReferenceSource(): void
    {
        // Arrange
        $provider = new ImplementationInstanceProvider(FakeBaseClass::class, FakeClassExtendsBaseClass::class);

        // Act
        $source = $provider->getDependencySource();

        // Assert
        self::assertInstanceOf(ReferenceSource::class, $source);
        self::assertSame(FakeClassExtendsBaseClass::class, $source->targetId);
    }

    public function testGetDependencySource_OnObjectInstanceProvider_ReturnsLeafSource(): void
    {
        // Arrange
        $provider = new ObjectInstanceProvider(FakeClassNoConstructor::class, new FakeClassNoConstructor());

        // Act
        $source = $provider->getDependencySource();

        // Assert
        self::assertInstanceOf(LeafSource::class, $source);
    }
}
