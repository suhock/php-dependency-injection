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
        $container = $this->createContainer();

        self::assertSame(
            $container,
            $container->addTransientClass(FakeClassNoConstructor::class)
        );
        $this->assertTransient($container, FakeClassNoConstructor::class);
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
        $container = $this->createContainer()
            ->addTransientClass(FakeClassExtendsNoConstructor::class);

        self::assertSame(
            $container,
            $container->addTransientImplementation(FakeClassNoConstructor::class, FakeClassExtendsNoConstructor::class)
        );

        $this->assertTransient($container, FakeClassNoConstructor::class, FakeClassExtendsNoConstructor::class);
    }

    public function testAddTransientImplementation_SameClass(): void
    {
        self::expectExceptionObject(
            new ImplementationException(FakeClassNoConstructor::class, FakeClassNoConstructor::class)
        );

        $this->createContainer()
            ->addTransientImplementation(FakeClassNoConstructor::class, FakeClassNoConstructor::class);
    }

    public function testAddTransientImplementation_NotSubClass(): void
    {
        self::expectExceptionObject(
            new ImplementationException(FakeClassExtendsNoConstructor::class, FakeClassNoConstructor::class)
        );
        $this->createContainer()
            ->addTransientImplementation(FakeClassExtendsNoConstructor::class, FakeClassNoConstructor::class);
    }

    public function testAddTransientFactory(): void
    {
        $container = $this->createContainer();

        self::assertSame(
            $container,
            $container->addTransientFactory(
                FakeClassNoConstructor::class,
                fn () => new FakeClassExtendsNoConstructor()
            )
        );

        $this->assertTransient($container, FakeClassNoConstructor::class, FakeClassExtendsNoConstructor::class);
    }

    public function testAddTransientFactory_null(): void
    {
        self::assertNull(
            $this->createContainer()
                ->addTransientFactory(FakeClassNoConstructor::class, fn () => null)
                ->get(FakeClassNoConstructor::class)
        );
    }

    public function testAddTransientFactory_WrongReturnType(): void
    {
        self::expectExceptionObject(
            new InstanceTypeException(FakeClassExtendsNoConstructor::class, new FakeClassNoConstructor())
        );

        $this->createContainer()
            ->addTransientFactory(FakeClassExtendsNoConstructor::class, fn () => new FakeClassNoConstructor())
            ->get(FakeClassExtendsNoConstructor::class);
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
                    return is_subclass_of($className, FakeClassNoConstructor::class);
                }
            });

        self::assertTransient($container, FakeClassExtendsNoConstructor::class);

        self::expectExceptionObject(new UnresolvedClassException(FakeClassNoConstructor::class));
        $container->get(FakeClassNoConstructor::class);
    }

    public function testAddTransientNamespace(): void
    {
        $this->assertTransient(
            $this->createContainer()
                ->addTransientNamespace(__NAMESPACE__),
            FakeClassNoConstructor::class
        );
    }

    public function testAddTransientInterface(): void
    {
        $this->assertTransient(
            $this->createContainer()
                ->addTransientInterface(FakeClassNoConstructor::class),
            FakeClassExtendsNoConstructor::class
        );
    }
}
