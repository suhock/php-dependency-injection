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
 * Test suite for {@see ContainerSingletonBuilderTrait}.
 */
final class ContainerSingletonBuilderTraitTest extends AbstractDependencyInjectionTestCase
{
    public function testAddSingleton_WithClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingleton(FakeClassNoConstructor::class)->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $sameInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingletonFactory_WithSelfParameter_GetReturnsConfiguredInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingletonFactory(
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

    public function testAddSingleton_WithImplementation_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingleton(FakeClassExtendsBaseClass::class)
            ->addSingleton(FakeBaseClass::class, FakeClassExtendsBaseClass::class)
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class);
        $sameInstance = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeBaseClass::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingleton_WithImplementationSameAsClass_ThrowsImplementationException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.implementationType (the invalid implementation is the case under test)
        $fn = static fn() => $builder->addSingleton(
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

    public function testAddSingleton_WithImplementationNotSubclass_ThrowsImplementationException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.implementationType (the invalid implementation is the case under test)
        $fn = static fn() => $builder->addSingleton(
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

    public function testAddSingletonFactory_WithFactory_GetReturnsValueFromFactory(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingletonFactory(
            FakeBaseClass::class,
            fn() => new FakeClassExtendsBaseClass(),
        )
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class);
        $sameInstance = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingletonFactory_WhenFactoryReturnsNull_GetThrowsInstanceTypeException(): void
    {
        // Arrange
        // @phpstan-ignore suhock.factoryReturnType (the wrong return type is the case under test)
        $container = self::createBuilder()->addSingletonFactory(FakeClassNoConstructor::class, fn() => null)
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

    public function testAddSingletonFactory_WhenReturnTypeIsWrong_GetThrowsInstanceTypeException(): void
    {
        // Arrange
        // @phpstan-ignore suhock.factoryReturnType (the wrong return type is the case under test)
        $container = self::createBuilder()->addSingletonFactory(
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

    public function testAddSingletonInstance_WithValidInstance_GetReturnsInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor())
            ->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $sameInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingletonInstance_WhenInstanceIsWrongType_ThrowsInstanceTypeException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.instanceType (the mismatched instance is the case under test)
        $fn = static fn() => $builder->addSingletonInstance(
            FakeClassExtendsBaseClass::class,
            new FakeClassNoConstructor(),
        );

        // Assert
        self::assertThrowsInstanceTypeException(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
            $fn,
        );
    }

    public function testAddSingleton_WithClosureAcceptingSupertypeOfClass_UsesClosureAsFactory(): void
    {
        // Arrange
        $expectedInstance = new FakeClassExtendsBaseClass();
        $container = self::createBuilder()->addSingletonInstance(FakeBaseClass::class, new FakeClassExtendsBaseClass())
            ->addSingleton(
                FakeClassExtendsBaseClass::class,
                fn(FakeBaseClass $inner) => $expectedInstance,
            )
            ->build();

        // Act
        $result = $container->get(FakeClassExtendsBaseClass::class);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testAddKeyedSingleton_WithClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')->build();

        // Act
        $instance = $container->get(FakeClassNoConstructor::class, 'key1');
        $sameInstance = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddKeyedSingleton_WithImplementation_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()->addSingleton(FakeClassExtendsBaseClass::class)
            ->addKeyedSingleton(FakeBaseClass::class, 'key1', FakeClassExtendsBaseClass::class)
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class, 'key1');
        $sameInstance = $container->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddKeyedSingleton_WithFactory_GetReturnsValueFromFactory(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedSingleton(
            FakeBaseClass::class,
            'key1',
            fn() => new FakeClassExtendsBaseClass(),
        )
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class, 'key1');
        $sameInstance = $container->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddKeyedSingleton_WithInstance_GetReturnsInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance)
            ->build();

        // Act
        $result = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testAddKeyedSingletonFactory_WithSelfParameter_GetByKeyReturnsConfiguredInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedSingletonFactory(
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
        $sameInstance = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame('test', $instance->string);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddKeyedSingletonFactory_WithFactory_GetByKeyReturnsValueFromFactory(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedSingletonFactory(
            FakeBaseClass::class,
            'key1',
            fn() => new FakeClassExtendsBaseClass(),
        )
            ->build();

        // Act
        $instance = $container->get(FakeBaseClass::class, 'key1');
        $sameInstance = $container->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddKeyedSingletonInstance_WithValidInstance_GetByKeyReturnsInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = self::createBuilder()->addKeyedSingletonInstance(FakeClassNoConstructor::class, 'key1', $expectedInstance)
            ->build();

        // Act
        $result = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testAddKeyedSingletonInstance_WhenInstanceIsWrongType_ThrowsInstanceTypeException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.instanceType (the mismatched instance is the case under test)
        $fn = static fn() => $builder->addKeyedSingletonInstance(
            FakeClassExtendsBaseClass::class,
            'key1',
            new FakeClassNoConstructor(),
        );

        // Assert
        self::assertThrowsInstanceTypeException(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
            $fn,
        );
    }
}
