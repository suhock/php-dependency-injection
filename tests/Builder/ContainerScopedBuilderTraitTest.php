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

/**
 * Test suite for {@see ContainerScopedBuilderTrait}.
 */
final class ContainerScopedBuilderTraitTest extends AbstractDependencyInjectionTestCase
{
    public function testAddScopedClass_WithValidClassName_GetReturnsPerScopeInstance(): void
    {
        // Arrange
        $container = self::createBuilder()->addScopedClass(FakeClassNoConstructor::class)->build();
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

    public function testAddScopedClass_WithMutator_GetReturnsMutatedInstance(): void
    {
        // Arrange
        $container = self::createBuilder()
            ->addScopedClass(
                FakeClassNoConstructor::class,
                function (FakeClassNoConstructor $obj) {
                    $obj->string = 'test';
                },
            )
            ->build();

        // Act
        $result = $container->createScope()->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame('test', $result->string);
    }

    public function testAddScopedImplementation_WithSubclass_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()
            ->addScopedClass(FakeClassExtendsBaseClass::class)
            ->addScopedImplementation(FakeBaseClass::class, FakeClassExtendsBaseClass::class)
            ->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($scope->get(FakeClassExtendsBaseClass::class), $instance);
    }

    public function testAddScopedImplementation_WithImplementationNotSubclass_ThrowsImplementationException(): void
    {
        // Arrange
        $builder = self::createBuilder();

        // Act
        $fn = static fn() => $builder->addScopedImplementation(
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

    public function testAddScopedFactory_WithFactory_GetReturnsValueFromFactoryWithScopeDependencies(): void
    {
        // Arrange
        $container = self::createBuilder()
            ->addScoped(FakeClassNoConstructor::class)
            ->addScopedFactory(
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

    public function testAddKeyedScopedClass_WithMutator_GetByKeyReturnsMutatedPerScopeInstance(): void
    {
        // Arrange
        $container = self::createBuilder()
            ->addKeyedScopedClass(
                FakeClassNoConstructor::class,
                'key1',
                function (FakeClassNoConstructor $obj) {
                    $obj->string = 'test';
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

    public function testAddKeyedScopedImplementation_WithSubclass_GetByKeyReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = self::createBuilder()
            ->addScopedClass(FakeClassExtendsBaseClass::class)
            ->addKeyedScopedImplementation(FakeBaseClass::class, 'key1', FakeClassExtendsBaseClass::class)
            ->build();
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertSame($scope->get(FakeClassExtendsBaseClass::class), $instance);
    }

    public function testAddKeyedScopedFactory_WithFactory_GetByKeyReturnsPerScopeInstance(): void
    {
        // Arrange
        $container = self::createBuilder()
            ->addKeyedScopedFactory(
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
