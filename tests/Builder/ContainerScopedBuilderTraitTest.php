<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use Suhock\DependencyInjection\AbstractDependencyInjectionTestCase;
use Suhock\DependencyInjection\Fakes\FakeBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Key;

/**
 * Test suite for {@see ContainerScopedBuilderTrait}.
 */
final class ContainerScopedBuilderTraitTest extends AbstractDependencyInjectionTestCase
{
    public function testAddScoped_WithClassName_GetReturnsPerScopeInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addScoped(FakeClassNoConstructor::class)->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeClassNoConstructor::class);
        $sameScopeInstance = $scope->get(FakeClassNoConstructor::class);
        $otherScopeInstance = $container->createScope()->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertSame($instance, $sameScopeInstance);
        self::assertNotSame($instance, $otherScopeInstance);
    }

    public function testAddScoped_WithSelfParameterFactory_GetReturnsConfiguredInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addScoped(
            FakeClassNoConstructor::class,
            function (FakeClassNoConstructor $obj): FakeClassNoConstructor {
                $obj->string = 'test';

                return $obj;
            },
        )
            ->build();

        // Act
        $result = $container->createScope()->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame('test', $result->string);
    }

    public function testAddScoped_WithImplementation_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()->addScoped(FakeClassExtendsBaseClass::class)
            ->addScoped(FakeBaseClass::class, FakeClassExtendsBaseClass::class)
            ->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($scope->get(FakeClassExtendsBaseClass::class), $instance);
    }

    public function testAddScoped_WithImplementationNotSubclass_ThrowsImplementationException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        // @phpstan-ignore suhock.implementationType (the invalid implementation is the case under test)
        $fn = static fn() => $builder->addScoped(
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

    public function testAddScoped_WithFactory_GetReturnsValueFromFactoryWithScopeDependencies(): void
    {
        // Arrange
        $container = self::createBuilder()->addScoped(FakeClassNoConstructor::class)
            ->addScoped(
                FakeClassWithConstructor::class,
                fn(FakeClassNoConstructor $obj) => new FakeClassWithConstructor($obj),
            )
            ->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeClassWithConstructor::class);

        // Assert
        self::assertSame($scope->get(FakeClassNoConstructor::class), $instance->obj);
    }

    public function testAddKeyedScoped_WithValidClass_GetByKeyReturnsPerScopeInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedScoped(FakeClassNoConstructor::class, 'key1')->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame($instance, $scope->get(FakeClassNoConstructor::class, 'key1'));
        self::assertFalse($scope->has(FakeClassNoConstructor::class));
    }

    public function testAddKeyedScoped_WithSelfParameterFactory_GetByKeyReturnsConfiguredPerScopeInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedScoped(
            FakeClassNoConstructor::class,
            'key1',
            function (#[Key('key1')] FakeClassNoConstructor $obj): FakeClassNoConstructor {
                $obj->string = 'test';

                return $obj;
            },
        )
            ->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame('test', $instance->string);
        self::assertSame($instance, $scope->get(FakeClassNoConstructor::class, 'key1'));
    }

    public function testAddKeyedScoped_WithImplementation_GetByKeyReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()->addScoped(FakeClassExtendsBaseClass::class)
            ->addKeyedScoped(FakeBaseClass::class, 'key1', FakeClassExtendsBaseClass::class)
            ->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($scope->get(FakeClassExtendsBaseClass::class), $instance);
    }

    public function testAddKeyedScoped_WithFactory_GetByKeyReturnsPerScopeInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addKeyedScoped(
            FakeBaseClass::class,
            'key1',
            fn() => new FakeClassExtendsBaseClass(),
        )
            ->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeBaseClass::class, 'key1');
        $otherScopeInstance = $container->createScope()->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($instance, $scope->get(FakeBaseClass::class, 'key1'));
        self::assertNotSame($instance, $otherScopeInstance);
    }
}
