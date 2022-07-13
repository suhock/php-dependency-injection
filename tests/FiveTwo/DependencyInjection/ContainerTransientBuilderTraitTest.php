<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use DateTime;
use FiveTwo\DependencyInjection\Provision\InstanceTypeException;
use LogicException;

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
        $container = $this->createContainer()->addTransientClass(FakeClassNoConstructor::class);

        $this->assertTransient($container, FakeClassNoConstructor::class);
    }

    public function testAddTransientClass_WithMutator_GetReturnsMutatedInstance(): void
    {
        $container = $this->createContainer()
            ->addTransientClass(
                FakeClassNoConstructor::class,
                function (FakeClassNoConstructor $obj) {
                    $obj->string = 'test';
                }
            );

        self::assertSame('test', $container->get(FakeClassNoConstructor::class)->string);
    }

    public function testAddTransientImplementation_WithSubclass_GetReturnsInstanceOfSubclass(): void
    {
        $container = $this->createContainer()
            ->addTransientClass(FakeClassExtendsNoConstructor::class)
            ->addTransientImplementation(FakeClassNoConstructor::class, FakeClassExtendsNoConstructor::class);

        $this->assertTransient(
            $container,
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddTransientImplementation_WithImplementationSameAsClass_ThrowsImplementationException(): void
    {
        $container = $this->createContainer();

        self::assertThrowsImplementationException(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
            static fn () => $container->addTransientImplementation(
                FakeClassNoConstructor::class,
                FakeClassNoConstructor::class
            )
        );
    }

    public function testAddTransientImplementation_WithImplementationNotSubclass_ThrowsImplementationException(): void
    {
        $container = $this->createContainer();

        self::assertThrowsImplementationException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            static fn () => $container->addTransientImplementation(
                FakeClassExtendsNoConstructor::class,
                FakeClassNoConstructor::class
            )
        );
    }

    public function testAddTransientFactory_WithFactory_GetReturnsValueFromFactory(): void
    {
        $container = $this->createContainer()
            ->addTransientFactory(
                FakeClassNoConstructor::class,
                fn () => new FakeClassExtendsNoConstructor()
            );

        $this->assertTransient(
            $container,
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddTransientFactory_WhenFactoryReturnsNull_GetThrowsWrappedInstanceTypeException(): void
    {
        $container = $this->createContainer()
            ->addTransientFactory(FakeClassNoConstructor::class, fn () => null);

        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            /** @param InstanceTypeException<FakeClassNoConstructor> $exception */
            static fn (InstanceTypeException $exception) => self::assertInstanceTypeException(
                FakeClassNoConstructor::class,
                null,
                $exception
            ),
            static fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddTransientFactory_WhenReturnTypeIsWrong_GetThrowsWrappedInstanceTypeException(): void
    {
        $container = $this->createContainer()
            ->addTransientFactory(
                FakeClassNoConstructor::class,
                fn () => new LogicException()
            );

        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            /** @param InstanceTypeException<FakeClassNoConstructor> $exception */
            static fn (InstanceTypeException $exception) => self::assertInstanceTypeException(
                FakeClassNoConstructor::class,
                LogicException::class,
                $exception
            ),
            static fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddTransientContainer_WithContainer_GetReturnsValueFromContainer(): void
    {
        $container = $this->createContainer()
            ->addTransientContainer(
                new FakeContainer([FakeClassNoConstructor::class => fn () => new FakeClassNoConstructor()])
            );

        $this->assertTransient($container, FakeClassNoConstructor::class);
    }

    public function testAdTransientContainer_WhenClassNotInContainer_GetThrowsClassNotFoundException(): void
    {
        $container = $this->createContainer()
            ->addTransientContainer(
                new FakeContainer([
                    FakeClassExtendsNoConstructor::class => fn () => new FakeClassExtendsNoConstructor()
                ])
            );

        self::assertThrowsClassNotFoundException(
            FakeClassNoConstructor::class,
            static fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddTransientNamespace_WithValidNamespace_GetReturnsInstance(): void
    {
        $container = $this->createContainer()->addTransientNamespace(__NAMESPACE__);

        $this->assertTransient(
            $container,
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
        );
    }

    public function testAddTransientNamespace_WithClassNotInNamespace_GetThrowsClassNotFoundException(): void
    {
        $container = $this->createContainer()->addTransientNamespace(__NAMESPACE__);

        self::assertThrowsClassNotFoundException(
            DateTime::class,
            static fn () => $container->get(DateTime::class)
        );
    }

    public function testAddTransientInterface_WithValidImplementation_GetReturnsInstance(): void
    {
        $container = $this->createContainer()->addTransientInterface(FakeClassNoConstructor::class);

        $this->assertTransient(
            $container,
            FakeClassExtendsNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddTransientInterface_WhenImplementationNotSubclass_GetThrowsClassNotFoundException(): void
    {
        $container = $this->createContainer()->addTransientInterface(FakeClassNoConstructor::class);

        self::assertThrowsClassNotFoundException(
            DateTime::class,
            static fn () => $container->get(DateTime::class)
        );
    }

    public function testAddTransientAttribute_WhenClassHasAttribute_GetReturnsInstance(): void
    {
        $container = $this->createContainer()->addTransientAttribute(FakeAttribute::class);

        $this->assertTransient($container, FakeClassWithAttribute::class);
    }

    public function testAddTransientAttribute_WhenClassDoesNotHaveAttribute_GetThrowsClassNotFoundException(): void
    {
        $container = $this->createContainer()->addTransientAttribute(FakeAttribute::class);

        self::assertThrowsClassNotFoundException(DateTime::class, static fn () => $container->get(DateTime::class));
    }
}
