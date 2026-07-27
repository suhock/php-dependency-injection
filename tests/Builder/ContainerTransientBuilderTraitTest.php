<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use LogicException;
use Suhock\DependencyInjection\AbstractDependencyInjectionTestCase;
use Suhock\DependencyInjection\Fakes\FakeBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\InstanceProvider\InstanceTypeException;
use Suhock\DependencyInjection\Key;

/**
 * Test suite for {@see ContainerTransientBuilderTrait}.
 */
final class ContainerTransientBuilderTraitTest extends AbstractDependencyInjectionTestCase
{
    public function testAddTransient_WithClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = self::createBuilder()->addTransient(FakeClassNoConstructor::class)->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $newInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertInstanceOf(FakeClassNoConstructor::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransient_WithSelfParameterFactory_GetReturnsConfiguredInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addTransient(
            FakeClassNoConstructor::class,
            function (FakeClassNoConstructor $obj): FakeClassNoConstructor {
                $obj->string = 'test';

                return $obj;
            },
        )
            ->build();

        // Act
        $result = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame('test', $result->string);
    }

    public function testAddTransient_WithImplementation_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()->addTransient(FakeClassExtendsBaseClass::class)
            ->addTransient(FakeBaseClass::class, FakeClassExtendsBaseClass::class)
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class);
        $newInstance = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransient_WithImplementationSameAsClass_ThrowsImplementationException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.implementationType (the invalid implementation is the case under test)
        $fn = static fn() => $builder->addTransient(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
            $fn,
        );
    }

    public function testAddTransient_WithImplementationNotSubclass_ThrowsImplementationException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.implementationType (the invalid implementation is the case under test)
        $fn = static fn() => $builder->addTransient(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
            $fn,
        );
    }

    public function testAddTransient_WithFactory_GetReturnsValueFromFactory(): void
    {
        // Arrange
        $container = self::createBuilder()->addTransient(
            FakeBaseClass::class,
            fn() => new FakeClassExtendsBaseClass(),
        )
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class);
        $newInstance = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransient_WhenFactoryReturnsNull_GetThrowsWrappedInstanceTypeException(): void
    {
        // Arrange
        // @phpstan-ignore suhock.factoryReturnType (the wrong return type is the case under test)
        $container = self::createBuilder()->addTransient(FakeClassNoConstructor::class, fn() => null)
            ->build();

        // Act
        $fn = static fn() => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn(InstanceTypeException $exception) => self::assertInstanceTypeException(
                FakeClassNoConstructor::class,
                null,
                $exception,
            ),
            $fn,
        );
    }

    public function testAddTransient_WhenFactoryReturnTypeIsWrong_GetThrowsWrappedInstanceTypeException(): void
    {
        // Arrange
        // @phpstan-ignore suhock.factoryReturnType (the wrong return type is the case under test)
        $container = self::createBuilder()->addTransient(
            FakeClassNoConstructor::class,
            fn() => new LogicException(),
        )
            ->build();

        // Act
        $fn = static fn() => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn(InstanceTypeException $exception) => self::assertInstanceTypeException(
                FakeClassNoConstructor::class,
                LogicException::class,
                $exception,
            ),
            $fn,
        );
    }

    public function testAddKeyedTransient_WithClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedTransient(FakeClassNoConstructor::class, 'key1')->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class, 'key1');
        $newInstance = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertInstanceOf(FakeClassNoConstructor::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddKeyedTransient_WithImplementation_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()->addTransient(FakeClassExtendsBaseClass::class)
            ->addKeyedTransient(FakeBaseClass::class, 'key1', FakeClassExtendsBaseClass::class)
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class, 'key1');
        $newInstance = $container->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddKeyedTransient_WithFactory_GetReturnsValueFromFactory(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedTransient(
            FakeBaseClass::class,
            'key1',
            fn() => new FakeClassExtendsBaseClass(),
        )
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class, 'key1');
        $newInstance = $container->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddKeyedTransient_WithSelfParameterFactory_GetByKeyReturnsConfiguredInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedTransient(
            FakeClassNoConstructor::class,
            'key1',
            function (#[Key('key1')] FakeClassNoConstructor $obj): FakeClassNoConstructor {
                $obj->string = 'test';

                return $obj;
            },
        )
            ->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class, 'key1');
        $newInstance = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame('test', $instance->string);
        self::assertNotSame($instance, $newInstance);
    }
}
