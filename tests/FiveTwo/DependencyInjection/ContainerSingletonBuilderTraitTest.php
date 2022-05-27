<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Exception;
use FiveTwo\DependencyInjection\Instantiation\DependencyTypeException;
use FiveTwo\DependencyInjection\Instantiation\ImplementationException;
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
     * @template TDependency
     * @template TImplementation
     *
     * @param class-string<TDependency> $className
     * @param class-string<TImplementation> $implementationClassName
     *
     * @return void
     * @psalm-param class-string<TImplementation>|'' $implementationClassName
     */
    private function assertSingleton(string $className, string $implementationClassName = ''): void
    {
        if ($implementationClassName === '') {
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
            $this->container->addSingletonInstance(NoConstructorTestClass::class, new NoConstructorTestClass())
        );
        $this->assertSingleton(NoConstructorTestClass::class);
    }

    public function testAddSingletonInstance_null(): void
    {
        $this->container->addSingletonInstance(NoConstructorTestClass::class, null);
        self::assertNull($this->container->get(NoConstructorTestClass::class));
    }

    public function testAddSingletonInstance_WrongType(): void
    {
        self::expectExceptionObject(
            new DependencyTypeException(NoConstructorTestSubClass::class, new NoConstructorTestClass())
        );
        $this->container->addSingletonInstance(NoConstructorTestSubClass::class, new NoConstructorTestClass());
    }

    public function testAddSingletonClass(): void
    {
        self::assertSame(
            $this->container,
            $this->container->addSingletonClass(NoConstructorTestClass::class)
        );
        $this->assertSingleton(NoConstructorTestClass::class);
    }

    public function testAddSingletonClass_Implementation(): void
    {
        $this->container->addSingletonClass(NoConstructorTestSubClass::class);

        self::assertSame(
            $this->container,
            $this->container->addSingletonClass(NoConstructorTestClass::class, NoConstructorTestSubClass::class)
        );
        $this->assertSingleton(NoConstructorTestClass::class, NoConstructorTestSubClass::class);
    }

    public function testAddSingletonClass_SameImplementation(): void
    {
        self::assertSame(
            $this->container,
            $this->container->addSingletonClass(NoConstructorTestClass::class, NoConstructorTestClass::class)
        );
        $this->assertSingleton(NoConstructorTestClass::class);
    }

    public function testAddSingletonClass_WrongImplementation(): void
    {
        $this->container->addSingletonInstance(Throwable::class, new Exception());
        $this->container->addSingletonInstance(RuntimeException::class, new RuntimeException());

        self::expectExceptionObject(
            new ImplementationException(NoConstructorTestSubClass::class, NoConstructorTestClass::class)
        );
        $this->container->addSingletonClass(NoConstructorTestSubClass::class, NoConstructorTestClass::class);
    }

    public function testAddSingletonFactory(): void
    {
        self::assertSame(
            $this->container,
            $this->container->addSingletonFactory(
                NoConstructorTestClass::class,
                fn() => new NoConstructorTestSubClass()
            )
        );
        $this->assertSingleton(NoConstructorTestClass::class, NoConstructorTestSubClass::class);
    }

    public function testAddSingletonFactory_null(): void
    {
        $this->container->addSingletonFactory(NoConstructorTestClass::class, fn () => null);
        self::assertNull($this->container->get(NoConstructorTestClass::class));
    }

    public function testAddSingletonFactory_WrongReturnType(): void
    {
        $this->container->addSingletonFactory(
            NoConstructorTestClass::class,
            fn() => new LogicException()
        );
        self::expectExceptionObject(
            new DependencyTypeException(NoConstructorTestClass::class, new LogicException())
        );
        $this->container->get(NoConstructorTestClass::class);
    }

    public function testAddSingletonContainer(): void
    {
        $this->container->addSingletonContainer(new class implements ContainerInterface {
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

        $this->assertSingleton(NoConstructorTestSubClass::class);
        self::expectExceptionObject(new UnresolvedClassException(NoConstructorTestClass::class));
        $this->container->get(NoConstructorTestClass::class);
    }

    public function testAddSingletonNamespace(): void
    {
        $this->container->addSingletonNamespace(__NAMESPACE__);
        $this->assertSingleton(NoConstructorTestClass::class);
    }

    public function testAddSingletonInterface(): void
    {
        $this->container->addSingletonInterface(NoConstructorTestClass::class);
        $this->assertSingleton(NoConstructorTestSubClass::class);
    }
}
