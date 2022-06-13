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
use LogicException;

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

        $instance = $container->get($className);
        self::assertInstanceOf($implementationClassName, $instance);
        self::assertSame($instance, $container->get($className));
    }

    public function testAddSingletonClass_NoMutator(): void
    {
        $this->assertSingleton(
            $this->createContainer()
                ->addSingletonClass(FakeClassNoConstructor::class),
            FakeClassNoConstructor::class
        );
    }

    public function testAddSingletonClass_WithMutator(): void
    {
        self::assertSame(
            'test',
            $this->createContainer()
                ->addSingletonClass(
                    FakeClassNoConstructor::class,
                    function (FakeClassNoConstructor $obj) {
                        $obj->string = 'test';
                    }
                )
                ->get(FakeClassNoConstructor::class)
                ?->string
        );
    }

    public function testAddSingletonImplementation(): void
    {
        $this->assertSingleton(
            $this->createContainer()
                ->addSingletonClass(FakeClassExtendsNoConstructor::class)
                ->addSingletonImplementation(FakeClassNoConstructor::class, FakeClassExtendsNoConstructor::class),
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddSingletonImplementation_ImplementationIsSameAsClass(): void
    {
        $container = $this->createContainer();

        self::assertImplementationException(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
            fn () => $container->addSingletonImplementation(
                FakeClassNoConstructor::class,
                FakeClassNoConstructor::class
            )
        );
    }

    public function testAddSingletonImplementation_ImplementationIsNotSubclass(): void
    {
        $container = $this->createContainer();

        self::assertImplementationException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            fn () => $container->addSingletonImplementation(
                FakeClassExtendsNoConstructor::class,
                FakeClassNoConstructor::class
            )
        );
    }

    public function testAddSingletonFactory(): void
    {
        $this->assertSingleton(
            $this->createContainer()
                ->addSingletonFactory(
                    FakeClassNoConstructor::class,
                    fn () => new FakeClassExtendsNoConstructor()
                ),
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddSingletonFactory_WorksWithNull(): void
    {
        self::assertNull(
            $this->createContainer()
                ->addSingletonFactory(FakeClassNoConstructor::class, fn () => null)
                ->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddSingletonFactory_Exception_ReturnTypeMismatch(): void
    {
        $container = $this->createContainer()
            ->addSingletonFactory(
                FakeClassNoConstructor::class,
                fn () => new LogicException()
            );

        self::assertInstanceTypeException(
            FakeClassNoConstructor::class,
            LogicException::class,
            fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddSingletonInstance(): void
    {
        $this->assertSingleton(
            $this->createContainer()
                ->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor()),
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
        );
    }

    public function testAddSingletonInstance_WorksWithNull(): void
    {
        self::assertNull(
            $this->createContainer()
                ->addSingletonInstance(FakeClassNoConstructor::class, null)
                ->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddSingletonInstance_Exception_TypeMismatch(): void
    {
        $container = $this->createContainer();

        self::assertInstanceTypeException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            fn () => $container->addSingletonInstance(
                FakeClassExtendsNoConstructor::class,
                new FakeClassNoConstructor()
            )
        );
    }

    public function testAddSingletonContainer(): void
    {
        $this->assertSingleton(
            $this->createContainer()
                ->addSingletonContainer(
                    new FakeContainer([FakeClassNoConstructor::class => fn () => new FakeClassNoConstructor()])
                ),
            FakeClassNoConstructor::class
        );
    }

    public function testAddSingletonContainer_Exception_NotInNestedContainer(): void
    {
        $container = $this->createContainer()
            ->addSingletonContainer(
                new FakeContainer([
                    FakeClassExtendsNoConstructor::class => fn () => new FakeClassExtendsNoConstructor()
                ])
            );

        self::assertUnresolvedClassException(
            FakeClassNoConstructor::class,
            fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddSingletonNamespace(): void
    {
        $this->assertSingleton(
            $this->createContainer()->addSingletonNamespace(__NAMESPACE__),
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
        );
    }

    public function testAddSingletonNamespace_Exception_NotInNamespace(): void
    {
        $container = $this->createContainer()
            ->addSingletonNamespace(__NAMESPACE__);

        self::assertUnresolvedClassException(
            DateTime::class,
            fn () => $container->get(DateTime::class)
        );
    }

    public function testAddSingletonInterface(): void
    {
        $this->assertSingleton(
            $this->createContainer()
                ->addSingletonInterface(FakeClassNoConstructor::class),
            FakeClassExtendsNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddSingletonInterface_Exception_NotSubclass(): void
    {
        $container = $this->createContainer()
            ->addSingletonInterface(FakeClassNoConstructor::class);

        self::assertUnresolvedClassException(DateTime::class, fn () => $container->get(DateTime::class));
    }
}
