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

use FiveTwo\DependencyInjection\InstanceProvision\ImplementationException;
use FiveTwo\DependencyInjection\InstanceProvision\InstanceTypeException;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see ContainerSingletonBuilderTrait}.
 */
class ContainerSingletonBuilderTraitTest extends TestCase
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

    public function testAddSingletonInstance(): void
    {
        $container = $this->createContainer();

        self::assertSame(
            $container,
            $container->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor())
        );
        $this->assertSingleton($container, FakeClassNoConstructor::class, FakeClassNoConstructor::class);
    }

    public function testAddSingletonInstance_null(): void
    {
        self::assertNull(
            $this->createContainer()
                ->addSingletonInstance(FakeClassNoConstructor::class, null)
                ->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddSingletonInstance_WrongType(): void
    {
        self::expectExceptionObject(
            new InstanceTypeException(FakeClassExtendsNoConstructor::class, new FakeClassNoConstructor())
        );

        $this->createContainer()
            ->addSingletonInstance(FakeClassExtendsNoConstructor::class, new FakeClassNoConstructor());
    }

    public function testAddSingletonClass_NoMutator(): void
    {
        $container = $this->createContainer();

        self::assertSame(
            $container,
            $container->addSingletonClass(FakeClassNoConstructor::class)
        );
        $this->assertSingleton($container, FakeClassNoConstructor::class, FakeClassNoConstructor::class);
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
        $container = $this->createContainer()
            ->addSingletonClass(FakeClassExtendsNoConstructor::class);

        self::assertSame(
            $container,
            $container->addSingletonImplementation(FakeClassNoConstructor::class, FakeClassExtendsNoConstructor::class)
        );
        $this->assertSingleton(
            $container,
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddSingletonImplementation_SameImplementation(): void
    {
        self::expectExceptionObject(
            new ImplementationException(FakeClassNoConstructor::class, FakeClassNoConstructor::class)
        );
        $this->createContainer()
            ->addSingletonImplementation(FakeClassNoConstructor::class, FakeClassNoConstructor::class);
    }

    public function testAddSingletonImplementation_WrongImplementation(): void
    {
        self::expectExceptionObject(
            new ImplementationException(FakeClassExtendsNoConstructor::class, FakeClassNoConstructor::class)
        );

        $this->createContainer()
            ->addSingletonImplementation(FakeClassExtendsNoConstructor::class, FakeClassNoConstructor::class);
    }

    public function testAddSingletonFactory(): void
    {
        $container = $this->createContainer();

        self::assertSame(
            $container,
            $container->addSingletonFactory(
                FakeClassNoConstructor::class,
                fn () => new FakeClassExtendsNoConstructor()
            )
        );
        $this->assertSingleton(
            $container,
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class
        );
    }

    public function testAddSingletonFactory_null(): void
    {
        self::assertNull(
            $this->createContainer()
                ->addSingletonFactory(FakeClassNoConstructor::class, fn () => null)
                ->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddSingletonFactory_WrongReturnType(): void
    {
        self::expectExceptionObject(
            new InstanceTypeException(FakeClassNoConstructor::class, new LogicException())
        );

        $this->createContainer()
            ->addSingletonFactory(
                FakeClassNoConstructor::class,
                fn () => new LogicException()
            )
            ->get(FakeClassNoConstructor::class);
    }

    public function testAddSingletonContainer(): void
    {
        $container = $this->createContainer()
            ->addSingletonContainer($inner = self::createMock(ContainerInterface::class));

        $inner->method('get')
            ->willReturn(new FakeClassExtendsNoConstructor());
        $inner->method('has')
            ->willReturnCallback(fn (string $className) => $className === FakeClassExtendsNoConstructor::class);

        $this->assertSingleton($container, FakeClassExtendsNoConstructor::class, FakeClassExtendsNoConstructor::class);
        self::expectExceptionObject(new UnresolvedClassException(FakeClassNoConstructor::class));
        $container->get(FakeClassNoConstructor::class);
    }

    public function testAddSingletonNamespace(): void
    {
        $this->assertSingleton(
            $this->createContainer()
                ->addSingletonNamespace(__NAMESPACE__),
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
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
}
