<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use DateTime;
use LogicException;
use Suhock\DependencyInjection\Fakes\FakeAttribute;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithAttribute;
use Suhock\DependencyInjection\Fakes\FakeContainer;
use Suhock\DependencyInjection\Provision\InstanceTypeException;

/**
 * Test suite for {@see ContainerSingletonBuilderTrait}.
 */
class ContainerSingletonBuilderTraitTest extends DependencyInjectionTestCase
{
    private function createContainer(): Container
    {
        return new Container();
    }

    public function testAddSingletonClass_WithValidClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingletonClass(FakeClassNoConstructor::class);

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $sameInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingletonClass_WithMutator_GetReturnsMutatedInstance(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonClass(
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

    public function testAddSingletonImplementation_WithSubclass_GetReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonClass(FakeClassExtendsNoConstructor::class)
            ->addSingletonImplementation(FakeClassNoConstructor::class, FakeClassExtendsNoConstructor::class);

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $sameInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingletonImplementation_WithImplementationSameAsClass_ThrowsImplementationException(): void
    {
        // Arrange
        $container = $this->createContainer();

        // Act
        $fn = static fn () => $container->addSingletonImplementation(
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

    public function testAddSingletonImplementation_WithImplementationNotSubclass_ThrowsImplementationException(): void
    {
        // Arrange
        $container = $this->createContainer();

        // Act
        $fn = static fn () => $container->addSingletonImplementation(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            $fn
        );
    }

    public function testAddSingletonFactory_WithFactory_GetReturnsValueFromFactory(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonFactory(
                FakeClassNoConstructor::class,
                fn () => new FakeClassExtendsNoConstructor()
            );

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $sameInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingletonFactory_WhenFactoryReturnsNull_GetThrowsInstanceTypeException(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonFactory(FakeClassNoConstructor::class, fn () => null);

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

    public function testAddSingletonFactory_WhenReturnTypeIsWrong_GetThrowsInstanceTypeException(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonFactory(
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

    public function testAddSingletonInstance_WithValidInstance_GetReturnsInstance(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor());

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
        $container = $this->createContainer();

        // Act
        $fn = static fn () => $container->addSingletonInstance(
            FakeClassExtendsNoConstructor::class,
            new FakeClassNoConstructor()
        );

        // Assert
        self::assertThrowsInstanceTypeException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            $fn
        );
    }

    public function testAddSingletonContainer_WithContainer_GetReturnsValueFromContainer(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonContainer(
                new FakeContainer([FakeClassNoConstructor::class => fn () => new FakeClassNoConstructor()])
            );

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $sameInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingletonContainer_WhenClassNotInContainer_GetThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonContainer(
                new FakeContainer([
                    FakeClassExtendsNoConstructor::class => fn () => new FakeClassExtendsNoConstructor()
                ])
            );

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testAddSingletonNamespace_WithValidNamespace_GetReturnsInstance(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingletonNamespace(__NAMESPACE__);

        // Act
        $instance = $container->get(FakeClassNoConstructor::class);
        $sameInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingletonNamespace_WithClassNotInNamespace_GetThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingletonNamespace(__NAMESPACE__);

        // Act
        $fn = static fn () => $container->get(DateTime::class);

        // Assert
        self::assertThrowsClassNotFoundException(DateTime::class, $fn);
    }

    public function testAddSingletonInterface_WithValidImplementation_GetReturnsInstance(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingletonInterface(FakeClassNoConstructor::class);

        // Act
        $instance = $container->get(FakeClassExtendsNoConstructor::class);
        $sameInstance = $container->get(FakeClassExtendsNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingletonInterface_WhenImplementationNotSubclass_GetThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingletonInterface(FakeClassNoConstructor::class);

        // Act
        $fn = static fn () => $container->get(DateTime::class);

        // Assert
        self::assertThrowsClassNotFoundException(DateTime::class, $fn);
    }

    public function testAddSingletonAttribute_WhenClassHasAttribute_GetReturnsInstance(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingletonAttribute(FakeAttribute::class);

        // Act
        $instance = $container->get(FakeClassWithAttribute::class);
        $sameInstance = $container->get(FakeClassWithAttribute::class);

        // Assert
        self::assertInstanceOf(FakeClassWithAttribute::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddSingletonAttribute_WhenClassDoesNotHaveAttribute_GetThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingletonAttribute(FakeAttribute::class);

        // Act
        $fn = static fn () => $container->get(DateTime::class);

        // Assert
        self::assertThrowsClassNotFoundException(DateTime::class, $fn);
    }

    public function testAddKeyedSingleton_WithClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = $this->createContainer()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

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
        $container = $this->createContainer()
            ->addSingletonClass(FakeClassExtendsNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', FakeClassExtendsNoConstructor::class);

        // Act
        $instance = $container->get(FakeClassNoConstructor::class, 'key1');
        $sameInstance = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddKeyedSingleton_WithFactory_GetReturnsValueFromFactory(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addKeyedSingleton(
                FakeClassNoConstructor::class,
                'key1',
                fn () => new FakeClassExtendsNoConstructor()
            );

        // Act
        $instance = $container->get(FakeClassNoConstructor::class, 'key1');
        $sameInstance = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertInstanceOf(FakeClassExtendsNoConstructor::class, $instance);
        self::assertSame($instance, $sameInstance);
    }

    public function testAddKeyedSingleton_WithInstance_GetReturnsInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance);

        // Act
        $result = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame($expectedInstance, $result);
    }
}
