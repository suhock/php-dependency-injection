<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use DateTime;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    private function getSubContainer(): ContainerInterface
    {
        $container = self::createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            /**
             * @param class-string $className
             * @psalm-suppress MixedMethodCall
             */
            fn (string $className) => new $className()
        );
        $container->method('has')
            ->willReturnCallback(fn (string $className) => is_subclass_of($className, FakeNoConstructorClass::class));

        return $container;
    }

    public function testRemove(): void
    {
        $this->container->addSingletonInstance(FakeNoConstructorClass::class, null);
        $this->container->remove(FakeNoConstructorClass::class);

        self::expectException(UnresolvedClassException::class);
        $this->container->get(FakeNoConstructorClass::class);
    }

    public function testTryGet_FactoryBeforeContainer(): void
    {
        self::assertStringStartsWith(
            __NAMESPACE__ . '\\',
            FakeNoConstructorClass::class,
            'Namespace mismatch. Test will be invalid.'
        );

        $goodInstance = new FakeNoConstructorSubclass();
        $this->container->addSingletonFactory(
            FakeNoConstructorSubclass::class,
            fn () => $goodInstance
        );

        $this->container->addSingletonContainer($this->getSubContainer());

        self::assertSame($goodInstance, $this->container->get(FakeNoConstructorSubclass::class));
    }

    public function testTryGet_ContainerBeforeNamespace(): void
    {
        self::assertStringStartsWith(
            __NAMESPACE__ . '\\',
            FakeNoConstructorClass::class,
            'Namespace mismatch. Test will be invalid.'
        );

        $goodInstance = new FakeNoConstructorSubclass();
        $subContainer = self::createMock(ContainerInterface::class);
        $subContainer->method('get')->willReturn($goodInstance);
        $subContainer->method('has')->willReturn(true);

        $this->container->addSingletonContainer($subContainer);

        $this->container->addSingletonNamespace(__NAMESPACE__);
        $this->container->addSingletonFactory(
            FakeNoConstructorClass::class,
            fn () => new FakeNoConstructorSubclass()
        );

        self::assertSame($goodInstance, $this->container->get(FakeNoConstructorSubclass::class));
    }

    public function testGet_Invalid(): void
    {
        self::expectExceptionObject(new UnresolvedClassException(FakeNoConstructorClass::class));
        $this->container->get(FakeNoConstructorClass::class);
    }

    public function testGet_CircularDependency(): void
    {
        $this->container->addSingletonFactory(FakeNoConstructorClass::class, fn (FakeNoConstructorClass $obj) => $obj);
        self::expectExceptionObject(new UnresolvedParameterException(
            'Closure::__invoke',
            'obj',
            FakeNoConstructorClass::class,
            new CircularDependencyException(FakeNoConstructorClass::class)
        ));
        $this->container->get(FakeNoConstructorClass::class);
    }

    public function testHas_None(): void
    {
        self::assertFalse($this->container->has(FakeNoConstructorClass::class));
    }

    public function testHas_FromInstance(): void
    {
        $this->container->addSingletonInstance(FakeNoConstructorClass::class, new FakeNoConstructorClass());
        self::assertTrue($this->container->has(FakeNoConstructorClass::class));
        self::assertFalse($this->container->has(FakeNoConstructorSubclass::class));
    }

    public function testHas_FromFactory(): void
    {
        $this->container->addSingletonFactory(FakeNoConstructorClass::class, fn () => new FakeNoConstructorClass());
        self::assertTrue($this->container->has(FakeNoConstructorClass::class));
        self::assertFalse($this->container->has(FakeNoConstructorSubclass::class));
    }

    public function testHas_FromSingletonContainer(): void
    {
        $this->container->addSingletonContainer($this->getSubContainer());
        self::assertTrue($this->container->has(FakeNoConstructorSubclass::class));
        self::assertFalse($this->container->has(FakeContextAwareClass::class));
    }

    public function testHas_FromTransientContainer(): void
    {
        $this->container->addTransientContainer($this->getSubContainer());
        self::assertTrue($this->container->has(FakeNoConstructorSubclass::class));
        self::assertFalse($this->container->has(FakeContextAwareClass::class));
    }

    public function testHas_FromNamespace(): void
    {
        $this->container->addSingletonNamespace(__NAMESPACE__);
        self::assertTrue($this->container->has(FakeNoConstructorClass::class));
        self::assertFalse($this->container->has(DateTime::class));
    }

    public function testHas_FromRootNamespace(): void
    {
        $this->container->addSingletonNamespace('');
        self::assertTrue($this->container->has(FakeNoConstructorClass::class));
        self::assertTrue($this->container->has(DateTime::class));
    }
}
