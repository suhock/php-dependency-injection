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

use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see Container}.
 */
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
            ->willReturnCallback(fn (string $className) => is_subclass_of($className, FakeClassNoConstructor::class));

        return $container;
    }

    public function testRemove(): void
    {
        $this->container->addSingletonInstance(FakeClassNoConstructor::class, null);
        $this->container->remove(FakeClassNoConstructor::class);

        self::expectException(UnresolvedClassException::class);
        $this->container->get(FakeClassNoConstructor::class);
    }

    public function testTryGet_FactoryBeforeContainer(): void
    {
        self::assertStringStartsWith(
            __NAMESPACE__ . '\\',
            FakeClassNoConstructor::class,
            'Namespace mismatch. Test will be invalid.'
        );

        $goodInstance = new FakeClassExtendsNoConstructor();
        $this->container->addSingletonFactory(
            FakeClassExtendsNoConstructor::class,
            fn () => $goodInstance
        );

        $this->container->addSingletonContainer($this->getSubContainer());

        self::assertSame($goodInstance, $this->container->get(FakeClassExtendsNoConstructor::class));
    }

    public function testTryGet_ContainerBeforeNamespace(): void
    {
        self::assertStringStartsWith(
            __NAMESPACE__ . '\\',
            FakeClassNoConstructor::class,
            'Namespace mismatch. Test will be invalid.'
        );

        $goodInstance = new FakeClassExtendsNoConstructor();
        $subContainer = self::createMock(ContainerInterface::class);
        $subContainer->method('get')->willReturn($goodInstance);
        $subContainer->method('has')->willReturn(true);

        $this->container->addSingletonContainer($subContainer);

        $this->container->addSingletonNamespace(__NAMESPACE__);
        $this->container->addSingletonFactory(
            FakeClassNoConstructor::class,
            fn () => new FakeClassExtendsNoConstructor()
        );

        self::assertSame($goodInstance, $this->container->get(FakeClassExtendsNoConstructor::class));
    }

    public function testGet_Invalid(): void
    {
        self::expectExceptionObject(new UnresolvedClassException(FakeClassNoConstructor::class));
        $this->container->get(FakeClassNoConstructor::class);
    }

    public function testGet_CircularDependency(): void
    {
        $this->container->addSingletonFactory(FakeClassNoConstructor::class, fn (FakeClassNoConstructor $obj) => $obj);
        self::expectExceptionObject(new UnresolvedParameterException(
            'Closure::__invoke',
            'obj',
            FakeClassNoConstructor::class,
            new CircularDependencyException(FakeClassNoConstructor::class)
        ));
        $this->container->get(FakeClassNoConstructor::class);
    }

    public function testHas_None(): void
    {
        self::assertFalse($this->container->has(FakeClassNoConstructor::class));
    }

    public function testHas_FromInstance(): void
    {
        $this->container->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor());
        self::assertTrue($this->container->has(FakeClassNoConstructor::class));
        self::assertFalse($this->container->has(FakeClassExtendsNoConstructor::class));
    }

    public function testHas_FromFactory(): void
    {
        $this->container->addSingletonFactory(FakeClassNoConstructor::class, fn () => new FakeClassNoConstructor());
        self::assertTrue($this->container->has(FakeClassNoConstructor::class));
        self::assertFalse($this->container->has(FakeClassExtendsNoConstructor::class));
    }

    public function testHas_FromSingletonContainer(): void
    {
        $this->container->addSingletonContainer($this->getSubContainer());
        self::assertTrue($this->container->has(FakeClassExtendsNoConstructor::class));
        self::assertFalse($this->container->has(FakeClassUsingContexts::class));
    }

    public function testHas_FromTransientContainer(): void
    {
        $this->container->addTransientContainer($this->getSubContainer());
        self::assertTrue($this->container->has(FakeClassExtendsNoConstructor::class));
        self::assertFalse($this->container->has(FakeClassUsingContexts::class));
    }

    public function testHas_FromNamespace(): void
    {
        $this->container->addSingletonNamespace(__NAMESPACE__);
        self::assertTrue($this->container->has(FakeClassNoConstructor::class));
        self::assertFalse($this->container->has(DateTime::class));
    }

    public function testHas_FromRootNamespace(): void
    {
        $this->container->addSingletonNamespace('');
        self::assertTrue($this->container->has(FakeClassNoConstructor::class));
        self::assertTrue($this->container->has(DateTime::class));
    }
}
