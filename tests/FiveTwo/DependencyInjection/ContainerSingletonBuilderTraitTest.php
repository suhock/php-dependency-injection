<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Exception;
use FiveTwo\DependencyInjection\Instantiation\ImplementationException;
use FiveTwo\DependencyInjection\Instantiation\InstanceTypeException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class ContainerSingletonBuilderTraitTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    /**
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param class-string<TClass> $className
     * @param class-string<TImplementation>|null $implementationClassName
     *
     * @return void
     */
    private function assertSingleton(string $className, ?string $implementationClassName = null): void
    {
        if ($implementationClassName === null) {
            $implementationClassName = $className;
        }

        $instance = $this->container->get($className);
        self::assertInstanceOf($implementationClassName, $instance);
        self::assertSame($instance, $this->container->get($className));
    }

    public function testAddSingletonInstance(): void
    {
        self::assertSame(
            $this->container,
            $this->container->addSingletonInstance(FakeNoConstructorClass::class, new FakeNoConstructorClass())
        );
        $this->assertSingleton(FakeNoConstructorClass::class);
    }

    public function testAddSingletonInstance_null(): void
    {
        $this->container->addSingletonInstance(FakeNoConstructorClass::class, null);
        self::assertNull($this->container->get(FakeNoConstructorClass::class));
    }

    public function testAddSingletonInstance_WrongType(): void
    {
        self::expectExceptionObject(
            new InstanceTypeException(FakeNoConstructorSubclass::class, new FakeNoConstructorClass())
        );
        $this->container->addSingletonInstance(FakeNoConstructorSubclass::class, new FakeNoConstructorClass());
    }

    public function testAddSingletonClass(): void
    {
        self::assertSame(
            $this->container,
            $this->container->addSingletonClass(FakeNoConstructorClass::class)
        );
        $this->assertSingleton(FakeNoConstructorClass::class);
    }

    public function testAddSingletonClass_Implementation(): void
    {
        $this->container->addSingletonClass(FakeNoConstructorSubclass::class);

        self::assertSame(
            $this->container,
            $this->container->addSingletonImplementation(FakeNoConstructorClass::class, FakeNoConstructorSubclass::class)
        );
        $this->assertSingleton(FakeNoConstructorClass::class, FakeNoConstructorSubclass::class);
    }

    public function testAddSingletonClass_SameImplementation(): void
    {
        self::expectExceptionObject(
            new ImplementationException(FakeNoConstructorClass::class, FakeNoConstructorClass::class)
        );
        $this->container->addSingletonImplementation(FakeNoConstructorClass::class, FakeNoConstructorClass::class);
    }

    public function testAddSingletonClass_WrongImplementation(): void
    {
        $this->container->addSingletonInstance(Throwable::class, new Exception());
        $this->container->addSingletonInstance(RuntimeException::class, new RuntimeException());

        self::expectExceptionObject(
            new ImplementationException(FakeNoConstructorSubclass::class, FakeNoConstructorClass::class)
        );
        $this->container->addSingletonImplementation(FakeNoConstructorSubclass::class, FakeNoConstructorClass::class);
    }

    public function testAddSingletonFactory(): void
    {
        self::assertSame(
            $this->container,
            $this->container->addSingletonFactory(
                FakeNoConstructorClass::class,
                fn () => new FakeNoConstructorSubclass()
            )
        );
        $this->assertSingleton(FakeNoConstructorClass::class, FakeNoConstructorSubclass::class);
    }

    public function testAddSingletonFactory_null(): void
    {
        $this->container->addSingletonFactory(FakeNoConstructorClass::class, fn () => null);
        self::assertNull($this->container->get(FakeNoConstructorClass::class));
    }

    public function testAddSingletonFactory_WrongReturnType(): void
    {
        $this->container->addSingletonFactory(
            FakeNoConstructorClass::class,
            fn () => new LogicException()
        );
        self::expectExceptionObject(
            new InstanceTypeException(FakeNoConstructorClass::class, new LogicException())
        );
        $this->container->get(FakeNoConstructorClass::class);
    }

    public function testAddSingletonContainer(): void
    {
        $this->container->addSingletonContainer(
            $inner = self::createMock(ContainerInterface::class)
        );
        $inner->method('get')
            ->willReturn(new FakeNoConstructorSubclass());
        $inner->method('has')
            ->willReturnCallback(fn (string $className) => $className === FakeNoConstructorSubclass::class);

        $this->assertSingleton(FakeNoConstructorSubclass::class);
        self::expectExceptionObject(new UnresolvedClassException(FakeNoConstructorClass::class));
        $this->container->get(FakeNoConstructorClass::class);
    }

    public function testAddSingletonNamespace(): void
    {
        $this->container->addSingletonNamespace(__NAMESPACE__);
        $this->assertSingleton(FakeNoConstructorClass::class);
    }

    public function testAddSingletonInterface(): void
    {
        $this->container->addSingletonInterface(FakeNoConstructorClass::class);
        $this->assertSingleton(FakeNoConstructorSubclass::class);
    }
}
