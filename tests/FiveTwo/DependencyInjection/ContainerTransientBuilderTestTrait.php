<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\DependencyTypeException;
use FiveTwo\DependencyInjection\Instantiation\ImplementationException;
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
            $this->container->addTransientClass(NoConstructorTestClass::class)
        );

        $this->assertTransient(NoConstructorTestClass::class);
    }

    public function testAddTransientClass_Implementation(): void
    {
        $this->container->addTransientClass(NoConstructorTestSubClass::class);

        self::assertSame(
            $this->container,
            $this->container->addTransientClass(NoConstructorTestClass::class, NoConstructorTestSubClass::class)
        );

        $this->assertTransient(NoConstructorTestClass::class, NoConstructorTestSubClass::class);
    }

    public function testAddTransientClass_SameImplementation(): void
    {
        self::assertSame(
            $this->container,
            $this->container->addTransientClass(NoConstructorTestClass::class, NoConstructorTestClass::class)
        );

        $this->assertTransient(NoConstructorTestClass::class);
    }

    public function testAddTransientClass_WrongImplementation(): void
    {
        self::expectExceptionObject(
            new ImplementationException(NoConstructorTestSubClass::class, NoConstructorTestClass::class)
        );
        $this->container->addTransientClass(NoConstructorTestSubClass::class, NoConstructorTestClass::class);
    }

    public function testAddTransientFactory(): void
    {
        self::assertSame(
            $this->container,
            $this->container->addTransientFactory(
                NoConstructorTestClass::class,
                fn() => new NoConstructorTestSubClass()
            )
        );

        $this->assertTransient(NoConstructorTestClass::class, NoConstructorTestSubClass::class);
    }

    public function testAddTransientFactory_null(): void
    {
        $this->container->addTransientFactory(NoConstructorTestClass::class, fn () => null);
        self::assertNull($this->container->get(NoConstructorTestClass::class));
    }

    public function testAddTransientFactory_WrongReturnType(): void
    {
        $this->container->addTransientFactory(NoConstructorTestSubClass::class, fn() => new NoConstructorTestClass());
        self::expectExceptionObject(
            new DependencyTypeException(NoConstructorTestSubClass::class, new NoConstructorTestClass())
        );
        $this->container->get(NoConstructorTestSubClass::class);
    }

    public function testAddTransientContainer(): void
    {
        $this->container->addTransientContainer(new class implements ContainerInterface {
            public function get(string $className): ?object
            {
                return new $className();
            }

            /**
             * @param class-string $className
             *
             * @return bool
             */
            public function has(string $className): bool
            {
                return is_subclass_of($className, NoConstructorTestClass::class);
            }
        });

        self::assertTransient(NoConstructorTestSubClass::class);
        self::expectExceptionObject(new UnresolvedClassException(NoConstructorTestClass::class));
        $this->container->get(NoConstructorTestClass::class);
    }

    public function testAddTransientNamespace(): void
    {
        $this->container->addTransientNamespace(__NAMESPACE__);
        $this->assertTransient(NoConstructorTestClass::class);
    }

    public function testAddTransientInterface(): void
    {
        $this->container->addTransientInterface(NoConstructorTestClass::class);
        $this->assertTransient(NoConstructorTestSubClass::class);
    }
}
