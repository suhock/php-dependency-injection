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
use FiveTwo\DependencyInjection\InstanceProvision\ImplementationException;
use FiveTwo\DependencyInjection\InstanceProvision\InstanceTypeException;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see ContainerTransientBuilderTrait}.
 */
class ContainerTransientBuilderTraitTest extends TestCase
{
    private function createContainer(): Container
    {
        return new Container();
    }

    /**
     * @param Container $container
     * @param class-string $className
     * @param class-string|null $implementationClassName
     *
     * @return void
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

    public function testAddTransientClass_NoMutator(): void
    {
        $this->assertTransient(
            $this->createContainer()
                ->addTransientClass(FakeClassNoConstructor::class),
            FakeClassNoConstructor::class
        );
    }

    public function testAddTransientClass_WithMutator(): void
    {
        self::assertSame(
            'test',
            $this->createContainer()
                ->addTransientClass(
                    FakeClassNoConstructor::class,
                    function (FakeClassNoConstructor $obj) {
                        $obj->string = 'test';
                    }
                )
                ->get(FakeClassNoConstructor::class)
                ?->string
        );
    }

    public function testAddTransientImplementation(): void
    {
        $this->assertTransient(
            $this->createContainer()
                ->addTransientClass(FakeClassExtendsNoConstructor::class)
                ->addTransientImplementation(FakeClassNoConstructor::class, FakeClassExtendsNoConstructor::class),
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddTransientImplementation_ImplementationIsSameAsClass(): void
    {
        $container = $this->createContainer();

        $this->expectExceptionObject(
            new ImplementationException(FakeClassNoConstructor::class, FakeClassNoConstructor::class)
        );
        $container->addTransientImplementation(FakeClassNoConstructor::class, FakeClassNoConstructor::class);
    }

    public function testAddTransientImplementation_ImplementationIsNotSubclass(): void
    {
        $container = $this->createContainer();

        $this->expectExceptionObject(
            new ImplementationException(FakeClassExtendsNoConstructor::class, FakeClassNoConstructor::class)
        );
        $container->addTransientImplementation(FakeClassExtendsNoConstructor::class, FakeClassNoConstructor::class);
    }

    public function testAddTransientFactory(): void
    {
        $this->assertTransient(
            $this->createContainer()
                ->addTransientFactory(
                    FakeClassNoConstructor::class,
                    fn () => new FakeClassExtendsNoConstructor()
                ),
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddTransientFactory_WorksWithNull(): void
    {
        self::assertNull(
            $this->createContainer()
                ->addTransientFactory(FakeClassNoConstructor::class, fn () => null)
                ->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddTransientFactory_Exception_ReturnTypeMismatch(): void
    {
        $container = $this->createContainer()
            ->addTransientFactory(
                FakeClassNoConstructor::class, fn() => new LogicException()
            );

        $this->expectExceptionObject(
            new InstanceTypeException(FakeClassNoConstructor::class, new LogicException())
        );
        $container->get(FakeClassNoConstructor::class);
    }

    public function testAddTransientContainer(): void
    {
        $this->assertTransient(
            $this->createContainer()
                ->addTransientContainer(
                    new FakeContainer([FakeClassNoConstructor::class => fn() => new FakeClassNoConstructor()])
                ),
            FakeClassNoConstructor::class
        );
    }

    public function testAddTransientContainer_NotInNestedContainer(): void
    {
        $container =$this->createContainer()
            ->addTransientContainer(
                new FakeContainer([FakeClassExtendsNoConstructor::class => fn() => new FakeClassExtendsNoConstructor()])
            );

        $this->expectExceptionObject(new UnresolvedClassException(FakeClassNoConstructor::class));
        $container->get(FakeClassNoConstructor::class);
    }

    public function testAddTransientNamespace(): void
    {
        $this->assertTransient(
            $this->createContainer()->addTransientNamespace(__NAMESPACE__),
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
        );
    }

    public function testAddTransientNamespace_Exception_NotInNamespace(): void
    {
        $container = $this->createContainer()
            ->addTransientNamespace(__NAMESPACE__);

        $this->expectExceptionObject(new UnresolvedClassException(DateTime::class));
        $container->get(DateTime::class);
    }

    public function testAddTransientInterface(): void
    {
        $this->assertTransient(
            $this->createContainer()
                ->addTransientInterface(FakeClassNoConstructor::class),
            FakeClassExtendsNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddTransientInterface_Exception_NotSubclass(): void
    {
        $container = $this->createContainer()
            ->addTransientInterface(FakeClassNoConstructor::class);

        $this->expectExceptionObject(new UnresolvedClassException(DateTime::class));
        $container->get(DateTime::class);
    }
}
