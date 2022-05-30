<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\ImplementationException;
use FiveTwo\DependencyInjection\Instantiation\InstanceTypeException;
use PHPUnit\Framework\TestCase;

class ContainerTransientBuilderTestTrait extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    /**
     * @param class-string $className
     * @param class-string $implementationClassName
     *
     * @return void
     * @psalm-param class-string|'' $implementationClassName
     */
    private function assertTransient(string $className, string $implementationClassName = ''): void
    {
        if ($implementationClassName === '') {
            $implementationClassName = $className;
        }

        $instance = $this->container->get($className);
        self::assertInstanceOf($implementationClassName, $instance);

        $newInstance = $this->container->get($className);
        self::assertInstanceOf($implementationClassName, $newInstance);
        self::assertNotSame($instance, $newInstance);
    }

    public function testAddTransientClass(): void
    {
        self::assertSame(
            $this->container,
            $this->container->addTransientClass(FakeNoConstructorClass::class)
        );

        $this->assertTransient(FakeNoConstructorClass::class);
    }

    public function testAddTransientImplementation(): void
    {
        $this->container->addTransientClass(FakeNoConstructorSubclass::class);

        self::assertSame(
            $this->container,
            $this->container->addTransientImplementation(FakeNoConstructorClass::class, FakeNoConstructorSubclass::class)
        );

        $this->assertTransient(FakeNoConstructorClass::class, FakeNoConstructorSubclass::class);
    }

    public function testAddTransientImplementation_SameClass(): void
    {
        self::expectExceptionObject(
            new ImplementationException(FakeNoConstructorClass::class, FakeNoConstructorClass::class)
        );
        $this->container->addTransientImplementation(FakeNoConstructorClass::class, FakeNoConstructorClass::class);

        $this->assertTransient(FakeNoConstructorClass::class);
    }

    public function testAddTransientImplementation_NotSubClass(): void
    {
        self::expectExceptionObject(
            new ImplementationException(FakeNoConstructorSubclass::class, FakeNoConstructorClass::class)
        );
        $this->container->addTransientImplementation(FakeNoConstructorSubclass::class, FakeNoConstructorClass::class);
    }

    public function testAddTransientFactory(): void
    {
        self::assertSame(
            $this->container,
            $this->container->addTransientFactory(
                FakeNoConstructorClass::class,
                fn () => new FakeNoConstructorSubclass()
            )
        );

        $this->assertTransient(FakeNoConstructorClass::class, FakeNoConstructorSubclass::class);
    }

    public function testAddTransientFactory_null(): void
    {
        $this->container->addTransientFactory(FakeNoConstructorClass::class, fn () => null);
        self::assertNull($this->container->get(FakeNoConstructorClass::class));
    }

    public function testAddTransientFactory_WrongReturnType(): void
    {
        $this->container->addTransientFactory(FakeNoConstructorSubclass::class, fn () => new FakeNoConstructorClass());
        self::expectExceptionObject(
            new InstanceTypeException(FakeNoConstructorSubclass::class, new FakeNoConstructorClass())
        );
        $this->container->get(FakeNoConstructorSubclass::class);
    }

    public function testAddTransientContainer(): void
    {
        $this->container->addTransientContainer(new class () implements ContainerInterface {
            public function get(string $className): ?object
            {
                /** @psalm-suppress MixedMethodCall */
                return new $className();
            }

            /**
             * @param class-string $className
             *
             * @return bool
             */
            public function has(string $className): bool
            {
                return is_subclass_of($className, FakeNoConstructorClass::class);
            }
        });

        self::assertTransient(FakeNoConstructorSubclass::class);
        self::expectExceptionObject(new UnresolvedClassException(FakeNoConstructorClass::class));
        $this->container->get(FakeNoConstructorClass::class);
    }

    public function testAddTransientNamespace(): void
    {
        $this->container->addTransientNamespace(__NAMESPACE__);
        $this->assertTransient(FakeNoConstructorClass::class);
    }

    public function testAddTransientInterface(): void
    {
        $this->container->addTransientInterface(FakeNoConstructorClass::class);
        $this->assertTransient(FakeNoConstructorSubclass::class);
    }
}
