<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use Exception;
use FiveTwo\DependencyInjection\ConstructorTestClass;
use FiveTwo\DependencyInjection\Container;
use FiveTwo\DependencyInjection\InjectorProvider;
use FiveTwo\DependencyInjection\NoConstructorTestClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class ContextInjectorTest extends TestCase
{
    public function testCall_NoContext(): void
    {
        $container = $this->createContainer();
        $injector = $container->getInjector();
        $container->context()
            ->addSingletonInstance(NoConstructorTestClass::class, $instance = new NoConstructorTestClass());

        self::assertSame($instance, $injector->call(fn (NoConstructorTestClass $obj) => $obj));
    }

    public function testCall_ContextOverride(): void
    {
        $container = $this->createContainer();
        $injector = $container->getInjector();
        $container->context()
            ->addSingletonInstance(NoConstructorTestClass::class, $instance0 = new NoConstructorTestClass());
        $container->context('context1')
            ->addSingletonInstance(NoConstructorTestClass::class, $instance1 = new NoConstructorTestClass());
        $container->context('context2')
            ->addSingletonInstance(NoConstructorTestClass::class, $instance2 = new NoConstructorTestClass());
        $container->context('context3');

        self::assertSame($instance0, $injector->call(fn (NoConstructorTestClass $obj) => $obj));
        self::assertSame(
            $instance1,
            $injector->call(
                #[Context('context1')]
                fn (NoConstructorTestClass $obj) => $obj
            )
        );
        self::assertSame(
            $instance2,
            $injector->call(
                #[Context('context1')]
                fn (#[Context('context2')] NoConstructorTestClass $obj) => $obj
            )
        );
        self::assertSame(
            $instance1,
            $injector->call(
                #[Context('context1')]
                fn (#[Context('context3')] NoConstructorTestClass $obj) => $obj
            )
        );
    }

    public function testInstantiation_NoContext(): void
    {
        $container = $this->createContainer();
        $injector = $container->getInjector();
        $container->context()->addSingletonClass(NoConstructorTestClass::class);

        self::assertInstanceOf(NoConstructorTestClass::class, $injector->instantiate(NoConstructorTestClass::class));
    }

    public function testInstantiation_ContextOverride(): void
    {
        $container = $this->createContainer();
        $injector = $container->getInjector();
        $container->context()
            ->addSingletonInstance(Throwable::class, new Exception());
        $container->context('context1')
            ->addSingletonInstance(Throwable::class, new Exception())
            ->addSingletonInstance(RuntimeException::class, $runtime1 = new RuntimeException());
        $container->context('context2')
            ->addSingletonInstance(Throwable::class, new Exception());
        $container->context('context3')
            ->addSingletonInstance(Throwable::class, $throwable3 = new Exception());
        $container->context('context4');

        self::assertSame($throwable3, $injector->instantiate(ConstructorTestClass::class)->throwable);
        self::assertSame($runtime1, $injector->instantiate(ConstructorTestClass::class)->runtimeException);
    }

    /**
     * @return ContextContainer<Container>
     */
    private function createContainer(): ContextContainer
    {
        return new ContextContainer(fn (InjectorProvider $injector) => new Container($injector));
    }
}
