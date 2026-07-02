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
 * Test suite for {@see ContainerSingletonBuilderTrait}.
 */
class ContainerSingletonBuilderTraitTest extends DependencyInjectionTestCase
{
    private function createContainer(): Container
    {
        return new Container();
    }

    /**
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param Container $container
     * @param class-string<TClass> $className
     * @param class-string<TImplementation>|null $implementationClassName
     *
     * @return void
     */
    private function assertSingleton(
        Container $container,
        string $className,
        ?string $implementationClassName = null
    ): void {
        $implementationClassName ??= $className;

        $expectedInstance = $container->get($className);
        self::assertInstanceOf($implementationClassName, $expectedInstance);
        self::assertSame($expectedInstance, $container->get($className));
    }

    public function testAddSingletonClass_WithValidClassName_GetReturnsInstanceOfClass(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingletonClass(FakeClassNoConstructor::class);

        // Act & Assert
        $this->assertSingleton($container, FakeClassNoConstructor::class);
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

        // Act & Assert
        $this->assertSingleton(
            $container,
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
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

        // Act & Assert
        $this->assertSingleton(
            $container,
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
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

        // Act & Assert
        $this->assertSingleton(
            $container,
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
        );
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

        // Act & Assert
        $this->assertSingleton($container, FakeClassNoConstructor::class);
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

        // Act & Assert
        $this->assertSingleton(
            $container,
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
        );
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

        // Act & Assert
        $this->assertSingleton(
            $container,
            FakeClassExtendsNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
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

        // Act & Assert
        $this->assertSingleton($container, FakeClassWithAttribute::class);
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
}
