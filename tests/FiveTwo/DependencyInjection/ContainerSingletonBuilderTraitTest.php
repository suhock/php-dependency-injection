<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\ImplementationException;
use FiveTwo\DependencyInjection\Instantiation\InstanceTypeException;
use LogicException;
use PHPUnit\Framework\TestCase;

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
            $container->addSingletonInstance(FakeNoConstructorClass::class, new FakeNoConstructorClass())
        );
        $this->assertSingleton($container, FakeNoConstructorClass::class, FakeNoConstructorClass::class);
    }

    public function testAddSingletonInstance_null(): void
    {
        self::assertNull(
            $this->createContainer()
                ->addSingletonInstance(FakeNoConstructorClass::class, null)
                ->get(FakeNoConstructorClass::class)
        );
    }

    public function testAddSingletonInstance_WrongType(): void
    {
        self::expectExceptionObject(
            new InstanceTypeException(FakeNoConstructorSubclass::class, new FakeNoConstructorClass())
        );

        $this->createContainer()
            ->addSingletonInstance(FakeNoConstructorSubclass::class, new FakeNoConstructorClass());
    }

    public function testAddSingletonClass_NoMutator(): void
    {
        $container = $this->createContainer();

        self::assertSame(
            $container,
            $container->addSingletonClass(FakeNoConstructorClass::class)
        );
        $this->assertSingleton($container, FakeNoConstructorClass::class, FakeNoConstructorClass::class);
    }

    public function testAddSingletonClass_WithMutator(): void
    {
        self::assertSame(
            'test',
            $this->createContainer()
                ->addSingletonClass(
                    FakeNoConstructorClass::class,
                    function (FakeNoConstructorClass $obj) {
                        $obj->string = 'test';
                    }
                )
                ->get(FakeNoConstructorClass::class)
                ?->string
        );
    }

    public function testAddSingletonImplementation(): void
    {
        $container = $this->createContainer()
            ->addSingletonClass(FakeNoConstructorSubclass::class);

        self::assertSame(
            $container,
            $container->addSingletonImplementation(FakeNoConstructorClass::class, FakeNoConstructorSubclass::class)
        );
        $this->assertSingleton(
            $container,
            FakeNoConstructorClass::class,
            FakeNoConstructorSubclass::class
        );
    }

    public function testAddSingletonImplementation_SameImplementation(): void
    {
        self::expectExceptionObject(
            new ImplementationException(FakeNoConstructorClass::class, FakeNoConstructorClass::class)
        );
        $this->createContainer()
            ->addSingletonImplementation(FakeNoConstructorClass::class, FakeNoConstructorClass::class);
    }

    public function testAddSingletonImplementation_WrongImplementation(): void
    {
        self::expectExceptionObject(
            new ImplementationException(FakeNoConstructorSubclass::class, FakeNoConstructorClass::class)
        );

        $this->createContainer()
            ->addSingletonImplementation(FakeNoConstructorSubclass::class, FakeNoConstructorClass::class);
    }

    public function testAddSingletonFactory(): void
    {
        $container = $this->createContainer();

        self::assertSame(
            $container,
            $container->addSingletonFactory(
                FakeNoConstructorClass::class,
                fn () => new FakeNoConstructorSubclass()
            )
        );
        $this->assertSingleton(
            $container,
            FakeNoConstructorClass::class,
            FakeNoConstructorSubclass::class
        );
    }

    public function testAddSingletonFactory_null(): void
    {
        self::assertNull(
            $this->createContainer()
                ->addSingletonFactory(FakeNoConstructorClass::class, fn () => null)
                ->get(FakeNoConstructorClass::class)
        );
    }

    public function testAddSingletonFactory_WrongReturnType(): void
    {
        self::expectExceptionObject(
            new InstanceTypeException(FakeNoConstructorClass::class, new LogicException())
        );

        $this->createContainer()
            ->addSingletonFactory(
                FakeNoConstructorClass::class,
                fn () => new LogicException()
            )
            ->get(FakeNoConstructorClass::class);
    }

    public function testAddSingletonContainer(): void
    {
        $container = $this->createContainer()
            ->addSingletonContainer($inner = self::createMock(ContainerInterface::class));

        $inner->method('get')
            ->willReturn(new FakeNoConstructorSubclass());
        $inner->method('has')
            ->willReturnCallback(fn (string $className) => $className === FakeNoConstructorSubclass::class);

        $this->assertSingleton($container, FakeNoConstructorSubclass::class, FakeNoConstructorSubclass::class);
        self::expectExceptionObject(new UnresolvedClassException(FakeNoConstructorClass::class));
        $container->get(FakeNoConstructorClass::class);
    }

    public function testAddSingletonNamespace(): void
    {
        $this->assertSingleton(
            $this->createContainer()
                ->addSingletonNamespace(__NAMESPACE__),
            FakeNoConstructorClass::class,
            FakeNoConstructorClass::class
        );
    }

    public function testAddSingletonInterface(): void
    {
        $this->assertSingleton(
            $this->createContainer()
                ->addSingletonInterface(FakeNoConstructorClass::class),
            FakeNoConstructorSubclass::class,
            FakeNoConstructorSubclass::class
        );
    }
}
