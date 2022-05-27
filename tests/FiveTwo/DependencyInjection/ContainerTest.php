<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    private function getSubContainer(): ContainerInterface|MockObject
    {
        $container = self::createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            /** @param class-string $className */
            fn(string $className) => new $className()
        );
        $container->method('has')
            ->willReturnCallback(fn(string $className) => is_subclass_of($className, NoConstructorTestClass::class));

        return $container;
    }

    public function testRemove(): void
    {
        $this->container->addSingletonInstance(NoConstructorTestClass::class, null);
        $this->container->remove(NoConstructorTestClass::class);

        self::expectException(UnresolvedClassException::class);
        $this->container->get(NoConstructorTestClass::class);
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
        $subContainer = self::createMock(ContainerInterface::class);
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
        self::expectExceptionObject(new UnresolvedParameterException(
            'Closure::__invoke',
            'obj',
            NoConstructorTestClass::class,
            new CircularDependencyException(NoConstructorTestClass::class)
        ));
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
