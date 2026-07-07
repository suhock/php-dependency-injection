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
use Suhock\DependencyInjection\Container;
use Suhock\DependencyInjection\Fakes\FakeBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\InstanceProvider\ClassInstanceProvider;

/**
 * Test suite for {@see ContainerScopedBuilderTrait}.
 */
final class ContainerScopedBuilderTraitTest extends AbstractDependencyInjectionTestCase
{
    private function createContainer(): Container
    {
        return Container::createDefault();
    }

    public function testAddScopedClass_WithValidClassName_GetReturnsPerScopeInstance(): void
    {
        // Arrange
        $container = $this->createContainer()->addScopedClass(FakeClassNoConstructor::class);
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
        $container = $this->createContainer()
            ->addScopedClass(
                FakeClassNoConstructor::class,
                function (FakeClassNoConstructor $obj) {
                    $obj->string = 'test';
                }
            );

        // Act
        $result = $container->createScope()->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame('test', $result->string);
    }

    public function testAddScopedImplementation_WithSubclass_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addScopedClass(FakeClassExtendsBaseClass::class)
            ->addScopedImplementation(FakeBaseClass::class, FakeClassExtendsBaseClass::class);
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
        $container = $this->createContainer();

        // Act
        $fn = static fn () => $container->addScopedImplementation(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
            $fn
        );
    }

    public function testAddScopedFactory_WithFactory_GetReturnsValueFromFactoryWithScopeDependencies(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addScoped(FakeClassNoConstructor::class)
            ->addScopedFactory(
                FakeClassWithConstructor::class,
                fn (FakeClassNoConstructor $obj) => new FakeClassWithConstructor($obj)
            );
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeClassWithConstructor::class);

        // Assert
        self::assertSame($scope->get(FakeClassNoConstructor::class), $instance->obj);
    }

    public function testAddKeyedScoped_WithValidClass_GetByKeyReturnsPerScopeInstance(): void
    {
        // Arrange
        $container = $this->createContainer()->addKeyedScoped(FakeClassNoConstructor::class, 'key1');
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame($instance, $scope->get(FakeClassNoConstructor::class, 'key1'));
        self::assertFalse($scope->has(FakeClassNoConstructor::class));
    }

    public function testAddKeyedScopedInstanceProvider_WithProvider_GetByKeyReturnsPerScopeInstance(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addKeyedScopedInstanceProvider(
                FakeClassNoConstructor::class,
                'key1',
                new ClassInstanceProvider(FakeClassNoConstructor::class)
            );
        $scope = $container->createScope();

        // Act
        $instance = $scope->get(FakeClassNoConstructor::class, 'key1');
        $otherScopeInstance = $container->createScope()->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame($instance, $scope->get(FakeClassNoConstructor::class, 'key1'));
        self::assertNotSame($instance, $otherScopeInstance);
    }

    public function testAddKeyedScopedClass_WithMutator_GetByKeyReturnsMutatedPerScopeInstance(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addKeyedScopedClass(
                FakeClassNoConstructor::class,
                'key1',
                function (FakeClassNoConstructor $obj) {
                    $obj->string = 'test';
                }
            );
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
        $container = $this->createContainer()
            ->addScopedClass(FakeClassExtendsBaseClass::class)
            ->addKeyedScopedImplementation(FakeBaseClass::class, 'key1', FakeClassExtendsBaseClass::class);
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
        $container = $this->createContainer()
            ->addKeyedScopedFactory(
                FakeBaseClass::class,
                'key1',
                fn () => new FakeClassExtendsBaseClass()
            );
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
