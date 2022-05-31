<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\ImplementationException;
use FiveTwo\DependencyInjection\Instantiation\InstanceTypeException;
use PHPUnit\Framework\TestCase;

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
        $container = $this->createContainer();

        self::assertSame(
            $container,
            $container->addTransientClass(FakeNoConstructorClass::class)
        );
        $this->assertTransient($container, FakeNoConstructorClass::class);
    }

    public function testAddTransientClass_WithMutator(): void
    {
        self::assertSame(
            'test',
            $this->createContainer()
                ->addTransientClass(
                    FakeNoConstructorClass::class,
                    function (FakeNoConstructorClass $obj) {
                        $obj->string = 'test';
                    }
                )
                ->get(FakeNoConstructorClass::class)
                ?->string
        );
    }

    public function testAddTransientImplementation(): void
    {
        $container = $this->createContainer()
            ->addTransientClass(FakeNoConstructorSubclass::class);

        self::assertSame(
            $container,
            $container->addTransientImplementation(FakeNoConstructorClass::class, FakeNoConstructorSubclass::class)
        );

        $this->assertTransient($container, FakeNoConstructorClass::class, FakeNoConstructorSubclass::class);
    }

    public function testAddTransientImplementation_SameClass(): void
    {
        self::expectExceptionObject(
            new ImplementationException(FakeNoConstructorClass::class, FakeNoConstructorClass::class)
        );

        $this->createContainer()
            ->addTransientImplementation(FakeNoConstructorClass::class, FakeNoConstructorClass::class);
    }

    public function testAddTransientImplementation_NotSubClass(): void
    {
        self::expectExceptionObject(
            new ImplementationException(FakeNoConstructorSubclass::class, FakeNoConstructorClass::class)
        );
        $this->createContainer()
            ->addTransientImplementation(FakeNoConstructorSubclass::class, FakeNoConstructorClass::class);
    }

    public function testAddTransientFactory(): void
    {
        $container = $this->createContainer();

        self::assertSame(
            $container,
            $container->addTransientFactory(
                FakeNoConstructorClass::class,
                fn () => new FakeNoConstructorSubclass()
            )
        );

        $this->assertTransient($container, FakeNoConstructorClass::class, FakeNoConstructorSubclass::class);
    }

    public function testAddTransientFactory_null(): void
    {
        self::assertNull(
            $this->createContainer()
                ->addTransientFactory(FakeNoConstructorClass::class, fn () => null)
                ->get(FakeNoConstructorClass::class)
        );
    }

    public function testAddTransientFactory_WrongReturnType(): void
    {
        self::expectExceptionObject(
            new InstanceTypeException(FakeNoConstructorSubclass::class, new FakeNoConstructorClass())
        );

        $this->createContainer()
            ->addTransientFactory(FakeNoConstructorSubclass::class, fn () => new FakeNoConstructorClass())
            ->get(FakeNoConstructorSubclass::class);
    }

    public function testAddTransientContainer(): void
    {
        $container = $this->createContainer()
            ->addTransientContainer(new class () implements ContainerInterface {
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

        self::assertTransient($container, FakeNoConstructorSubclass::class);

        self::expectExceptionObject(new UnresolvedClassException(FakeNoConstructorClass::class));
        $container->get(FakeNoConstructorClass::class);
    }

    public function testAddTransientNamespace(): void
    {
        $this->assertTransient(
            $this->createContainer()
                ->addTransientNamespace(__NAMESPACE__),
            FakeNoConstructorClass::class
        );
    }

    public function testAddTransientInterface(): void
    {
        $this->assertTransient(
            $this->createContainer()
                ->addTransientInterface(FakeNoConstructorClass::class),
            FakeNoConstructorSubclass::class
        );
    }
}
