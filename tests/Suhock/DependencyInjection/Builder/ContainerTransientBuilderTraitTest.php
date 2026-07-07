<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use DateTime;
use LogicException;
use ReflectionClass;
use Suhock\DependencyInjection\AbstractDependencyInjectionTestCase;
use Suhock\DependencyInjection\Container;
use Suhock\DependencyInjection\Fakes\FakeAttribute;
use Suhock\DependencyInjection\Fakes\FakeBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithAttribute;
use Suhock\DependencyInjection\Fakes\FakeContainer;
use Suhock\DependencyInjection\InstanceProvider\ClosureInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\InstanceTypeException;

/**
 * Test suite for {@see ContainerTransientBuilderTrait}.
 */
final class ContainerTransientBuilderTraitTest extends AbstractDependencyInjectionTestCase
{
    private function createContainer(): Container
    {
        return Container::createDefault();
    }

    public function testAddTransientClass_WithValidClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = $this->createContainer()->addTransientClass(FakeClassNoConstructor::class);

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $newInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertInstanceOf(FakeClassNoConstructor::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransientClass_WithMutator_GetReturnsMutatedInstance(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addTransientClass(
                FakeClassNoConstructor::class,
                function (FakeClassNoConstructor $obj) {
                    $obj->string = 'test';
                }
            );

        // Act
        $result = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame('test', $result->string);
    }

    public function testAddTransientImplementation_WithSubclass_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addTransientClass(FakeClassExtendsBaseClass::class)
            ->addTransientImplementation(FakeBaseClass::class, FakeClassExtendsBaseClass::class);

        // Act
        $instance = $container->get(FakeBaseClass::class);
        $newInstance = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransientImplementation_WithImplementationSameAsClass_ThrowsImplementationException(): void
    {
        // Arrange
        $container = $this->createContainer();

        // Act
        $fn = static fn () => $container->addTransientImplementation(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
            $fn
        );
    }

    public function testAddTransientImplementation_WithImplementationNotSubclass_ThrowsImplementationException(): void
    {
        // Arrange
        $container = $this->createContainer();

        // Act
        $fn = static fn () => $container->addTransientImplementation(
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

    public function testAddTransientFactory_WithFactory_GetReturnsValueFromFactory(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addTransientFactory(
                FakeBaseClass::class,
                fn () => new FakeClassExtendsBaseClass()
            );

        // Act
        $instance = $container->get(FakeBaseClass::class);
        $newInstance = $container->get(FakeBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransientFactory_WhenFactoryReturnsNull_GetThrowsWrappedInstanceTypeException(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addTransientFactory(FakeClassNoConstructor::class, fn () => null);

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn (InstanceTypeException $exception) => self::assertInstanceTypeException(
                FakeClassNoConstructor::class,
                null,
                $exception
            ),
            $fn
        );
    }

    public function testAddTransientFactory_WhenReturnTypeIsWrong_GetThrowsWrappedInstanceTypeException(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addTransientFactory(
                FakeClassNoConstructor::class,
                fn () => new LogicException()
            );

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn (InstanceTypeException $exception) => self::assertInstanceTypeException(
                FakeClassNoConstructor::class,
                LogicException::class,
                $exception
            ),
            $fn
        );
    }

    public function testAddTransientContainer_WithContainer_GetReturnsValueFromContainer(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addTransientContainer(
                new FakeContainer([FakeClassNoConstructor::class => fn () => new FakeClassNoConstructor()])
            );

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $newInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertInstanceOf(FakeClassNoConstructor::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAdTransientContainer_WhenClassNotInContainer_GetThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addTransientContainer(
                new FakeContainer([
                    FakeClassExtendsBaseClass::class => fn () => new FakeClassExtendsBaseClass()
                ])
            );

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testAddTransientNamespace_WithValidNamespace_GetReturnsInstance(): void
    {
        // Arrange
        $namespace = (new ReflectionClass(FakeClassNoConstructor::class))->getNamespaceName();
        $container = $this->createContainer()->addTransientNamespace($namespace);

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $newInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertInstanceOf(FakeClassNoConstructor::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransientNamespace_WithClassNotInNamespace_GetThrowsClassNotFoundException(): void
    {
        // Arrange
        $namespace = (new ReflectionClass(FakeClassNoConstructor::class))->getNamespaceName();
        $container = $this->createContainer()->addTransientNamespace($namespace);

        // Act
        $fn = static fn () => $container->get(DateTime::class);

        // Assert
        self::assertThrowsClassNotFoundException(DateTime::class, $fn);
    }

    public function testAddTransientInterface_WithValidImplementation_GetReturnsInstance(): void
    {
        // Arrange
        $container = $this->createContainer()->addTransientInterface(FakeBaseClass::class);

        // Act
        $instance = $container->get(FakeClassExtendsBaseClass::class);
        $newInstance = $container->get(FakeClassExtendsBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransientInterface_WhenImplementationNotSubclass_GetThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer()->addTransientInterface(FakeClassNoConstructor::class);

        // Act
        $fn = static fn () => $container->get(DateTime::class);

        // Assert
        self::assertThrowsClassNotFoundException(DateTime::class, $fn);
    }

    public function testAddTransientAttribute_WhenClassHasAttribute_GetReturnsInstance(): void
    {
        // Arrange
        $container = $this->createContainer()->addTransientAttribute(FakeAttribute::class);

        // Act
        $instance = $container->get(FakeClassWithAttribute::class);
        $newInstance = $container->get(FakeClassWithAttribute::class);

        // Assert
        self::assertInstanceOf(FakeClassWithAttribute::class, $instance);
        self::assertInstanceOf(FakeClassWithAttribute::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransientAttribute_WhenClassDoesNotHaveAttribute_GetThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer()->addTransientAttribute(FakeAttribute::class);

        // Act
        $fn = static fn () => $container->get(DateTime::class);

        // Assert
        self::assertThrowsClassNotFoundException(DateTime::class, $fn);
    }

    public function testAddKeyedTransient_WithClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = $this->createContainer()->addKeyedTransient(FakeClassNoConstructor::class, 'key1');

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
        $container = $this->createContainer()
            ->addTransientClass(FakeClassExtendsBaseClass::class)
            ->addKeyedTransient(FakeBaseClass::class, 'key1', FakeClassExtendsBaseClass::class);

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
        $container = $this->createContainer()
            ->addKeyedTransient(
                FakeBaseClass::class,
                'key1',
                fn () => new FakeClassExtendsBaseClass()
            );

        // Act
        $instance = $container->get(FakeBaseClass::class, 'key1');
        $newInstance = $container->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddKeyedTransientInstanceProvider_WithProvider_GetByKeyReturnsInstanceFromProvider(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addKeyedTransientInstanceProvider(
                FakeClassNoConstructor::class,
                'key1',
                new ClosureInstanceProvider(FakeClassNoConstructor::class, fn () => new FakeClassNoConstructor())
            );

        // Act
        $instance = $container->get(FakeClassNoConstructor::class, 'key1');
        $newInstance = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertNotSame($instance, $newInstance);
        self::assertFalse($container->has(FakeClassNoConstructor::class));
    }

    public function testAddKeyedTransientClass_WithMutator_GetByKeyReturnsMutatedInstance(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addKeyedTransientClass(
                FakeClassNoConstructor::class,
                'key1',
                function (FakeClassNoConstructor $obj) {
                    $obj->string = 'test';
                }
            );

        // Act
        $instance = $container->get(FakeClassNoConstructor::class, 'key1');
        $newInstance = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame('test', $instance->string);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddKeyedTransientImplementation_WithSubclass_GetByKeyReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addTransientClass(FakeClassExtendsBaseClass::class)
            ->addKeyedTransientImplementation(FakeBaseClass::class, 'key1', FakeClassExtendsBaseClass::class);

        // Act
        $instance = $container->get(FakeBaseClass::class, 'key1');
        $newInstance = $container->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddKeyedTransientFactory_WithFactory_GetByKeyReturnsNewValueFromFactory(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addKeyedTransientFactory(
                FakeBaseClass::class,
                'key1',
                fn () => new FakeClassExtendsBaseClass()
            );

        // Act
        $instance = $container->get(FakeBaseClass::class, 'key1');
        $newInstance = $container->get(FakeBaseClass::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
        self::assertNotSame($instance, $newInstance);
    }
}
