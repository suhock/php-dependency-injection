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
use Suhock\DependencyInjection\Provision\InstanceTypeException;

/**
 * Test suite for {@see ContainerTransientBuilderTrait}.
 */
class ContainerTransientBuilderTraitTest extends DependencyInjectionTestCase
{
    private function createContainer(): Container
    {
        return new Container();
    }

    /**
     * @template TClass of object
     * @template TImplementation of TClass
     * @param class-string<TClass> $className
     * @param class-string<TImplementation>|null $implementationClassName
     */
    private function assertTransient(
        Container $container,
        string $className,
        ?string $implementationClassName = null
    ): void {
        $implementationClassName ??= $className;

        $instance = $container->get($className);
        self::assertInstanceOf($implementationClassName, $instance);

        $newInstance = $container->get($className);
        self::assertInstanceOf($implementationClassName, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransientClass_WithValidClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = $this->createContainer()->addTransientClass(FakeClassNoConstructor::class);

        // Act & Assert
        $this->assertTransient($container, FakeClassNoConstructor::class);
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
            ->addTransientClass(FakeClassExtendsNoConstructor::class)
            ->addTransientImplementation(FakeClassNoConstructor::class, FakeClassExtendsNoConstructor::class);

        // Act & Assert
        $this->assertTransient(
            $container,
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
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

    public function testAddTransientFactory_WithFactory_GetReturnsValueFromFactory(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addTransientFactory(
                FakeClassNoConstructor::class,
                fn () => new FakeClassExtendsNoConstructor()
            );

        // Act & Assert
        $this->assertTransient(
            $container,
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
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

        // Act & Assert
        $this->assertTransient($container, FakeClassNoConstructor::class);
    }

    public function testAdTransientContainer_WhenClassNotInContainer_GetThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addTransientContainer(
                new FakeContainer([
                    FakeClassExtendsNoConstructor::class => fn () => new FakeClassExtendsNoConstructor()
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
        $container = $this->createContainer()->addTransientNamespace(__NAMESPACE__);

        // Act & Assert
        $this->assertTransient(
            $container,
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
        );
    }

    public function testAddTransientNamespace_WithClassNotInNamespace_GetThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer()->addTransientNamespace(__NAMESPACE__);

        // Act
        $fn = static fn () => $container->get(DateTime::class);

        // Assert
        self::assertThrowsClassNotFoundException(DateTime::class, $fn);
    }

    public function testAddTransientInterface_WithValidImplementation_GetReturnsInstance(): void
    {
        // Arrange
        $container = $this->createContainer()->addTransientInterface(FakeClassNoConstructor::class);

        // Act & Assert
        $this->assertTransient(
            $container,
            FakeClassExtendsNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
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

        // Act & Assert
        $this->assertTransient($container, FakeClassWithAttribute::class);
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
}
