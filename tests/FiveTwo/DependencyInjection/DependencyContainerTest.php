<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use DateTime;
use Exception;
use FiveTwo\DependencyInjection\Instantiation\ClassImplementationException;
use FiveTwo\DependencyInjection\Instantiation\DependencyTypeException;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class DependencyContainerTest extends TestCase
{
    private DependencyContainer $container;

    protected function setUp(): void
    {
        $this->container = new DependencyContainer();
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

    private function getSubContainer(): DependencyContainerInterface|MockObject
    {
        $container = self::createMock(DependencyContainerInterface::class);
        $container->method('get')->willReturnCallback(
            /** @param class-string $className */
            fn(string $className) => new $className()
        );
        $container->method('has')
            ->willReturnCallback(fn(string $className) => is_subclass_of($className, NoConstructorTestClass::class));

        return $container;
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
            new ClassImplementationException(NoConstructorTestSubClass::class, NoConstructorTestClass::class)
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
        $this->container->addSingletonContainer(new class implements DependencyContainerInterface {
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
            new ClassImplementationException(NoConstructorTestSubClass::class, NoConstructorTestClass::class)
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
        $this->container->addTransientContainer(new class implements DependencyContainerInterface {
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

    public function testTryGet_FactoryBeforeContainer(): void
    {
        self::assertStringStartsWith(
            __NAMESPACE__ . '\\',
            NoConstructorTestClass::class,
            "Namespace mismatch. Test will be invalid."
        );

        $goodInstance = new NoConstructorTestSubClass();
        $this->container->addSingletonFactory(
            NoConstructorTestSubClass::class,
            fn() => $goodInstance
        );

        /** @psalm-suppress PossiblyInvalidArgument can't use intersection types yet with Psalm */
        $this->container->addSingletonContainer($this->getSubContainer());

        self::assertSame($goodInstance, $this->container->get(NoConstructorTestSubClass::class));
    }

    public function testTryGet_ContainerBeforeNamespace(): void
    {
        self::assertStringStartsWith(
            __NAMESPACE__ . '\\',
            NoConstructorTestClass::class,
            "Namespace mismatch. Test will be invalid."
        );

        $goodInstance = new NoConstructorTestSubClass();
        $subContainer = self::createMock(DependencyContainerInterface::class);
        $subContainer->method('get')->willReturn($goodInstance);
        $subContainer->method('has')->willReturn(true);

        $this->container->addSingletonContainer($subContainer);

        $this->container->addSingletonNamespace(__NAMESPACE__);
        $this->container->addSingletonFactory(
            NoConstructorTestClass::class,
            fn() => new NoConstructorTestSubClass()
        );

        self::assertSame($goodInstance, $this->container->get(NoConstructorTestSubClass::class));
    }

    public function testGet_Invalid(): void
    {
        self::expectExceptionObject(new UnresolvedClassException(NoConstructorTestClass::class));
        $this->container->get(NoConstructorTestClass::class);
    }

    public function testGet_CircularDependency(): void
    {
        $this->container->addSingletonFactory(NoConstructorTestClass::class, fn(NoConstructorTestClass $obj) => $obj);
        self::expectExceptionObject(new CircularDependencyException(NoConstructorTestClass::class));
        $this->container->get(NoConstructorTestClass::class);
    }

    public function testHas_None(): void
    {
        self::assertFalse($this->container->has(NoConstructorTestClass::class));
    }

    public function testHas_FromInstance(): void
    {
        $this->container->addSingletonInstance(NoConstructorTestClass::class, new NoConstructorTestClass());
        self::assertTrue($this->container->has(NoConstructorTestClass::class));
        self::assertFalse($this->container->has(NoConstructorTestSubClass::class));
    }

    public function testHas_FromFactory(): void
    {
        $this->container->addSingletonFactory(NoConstructorTestClass::class, fn() => new NoConstructorTestClass());
        self::assertTrue($this->container->has(NoConstructorTestClass::class));
        self::assertFalse($this->container->has(NoConstructorTestSubClass::class));
    }

    public function testHas_FromSingletonContainer(): void
    {
        /** @psalm-suppress PossiblyInvalidArgument can't use intersection types yet with Psalm */
        $this->container->addSingletonContainer($this->getSubContainer());
        self::assertTrue($this->container->has(NoConstructorTestSubClass::class));
        self::assertFalse($this->container->has(ConstructorTestClass::class));
    }

    public function testHas_FromTransientContainer(): void
    {
        /** @psalm-suppress PossiblyInvalidArgument can't use intersection types yet with Psalm */
        $this->container->addTransientContainer($this->getSubContainer());
        self::assertTrue($this->container->has(NoConstructorTestSubClass::class));
        self::assertFalse($this->container->has(ConstructorTestClass::class));
    }

    public function testHas_FromNamespace(): void
    {
        $this->container->addSingletonNamespace(__NAMESPACE__);
        self::assertTrue($this->container->has(NoConstructorTestClass::class));
        self::assertFalse($this->container->has(DateTime::class));
    }

    public function testHas_FromRootNamespace(): void
    {
        $this->container->addSingletonNamespace('');
        self::assertTrue($this->container->has(NoConstructorTestClass::class));
        self::assertTrue($this->container->has(DateTime::class));
    }
}
