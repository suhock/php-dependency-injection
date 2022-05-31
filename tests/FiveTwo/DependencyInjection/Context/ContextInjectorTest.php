<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use Exception;
use FiveTwo\DependencyInjection\Container;
use FiveTwo\DependencyInjection\FakeContextAwareClass;
use FiveTwo\DependencyInjection\FakeNoConstructorClass;
use FiveTwo\DependencyInjection\InjectorProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class ContextInjectorTest extends TestCase
{
    public function testCall_NoContext(): void
    {
        $container = $this->createContainer();
        $injector = $container->getInjector();
        $container->context('default')
            ->addSingletonInstance(FakeNoConstructorClass::class, $instance = new FakeNoConstructorClass());
        $container->push('default');

        self::assertSame($instance, $injector->call(fn (FakeNoConstructorClass $obj) => $obj));
    }

    public function testCall_ContextOverride(): void
    {
        $container = $this->createContainer();
        $injector = $container->getInjector();

        $container->context('default')
            ->addSingletonInstance(FakeNoConstructorClass::class, $instance0 = new FakeNoConstructorClass());
        $container->context('context1')
            ->addSingletonInstance(FakeNoConstructorClass::class, $instance1 = new FakeNoConstructorClass());
        $container->context('context2')
            ->addSingletonInstance(FakeNoConstructorClass::class, $instance2 = new FakeNoConstructorClass());
        $container->context('context3');

        $container->push('default');
        self::assertSame(
            $instance0,
            $injector->call(fn (FakeNoConstructorClass $obj) => $obj)
        );
        self::assertSame(
            $instance1,
            $injector->call(
                #[Context('context1')]
                fn (FakeNoConstructorClass $obj) => $obj
            )
        );
        self::assertSame(
            $instance2,
            $injector->call(
                #[Context('context1')]
                fn (#[Context('context2')] FakeNoConstructorClass $obj) => $obj
            )
        );
        self::assertSame(
            $instance1,
            $injector->call(
                #[Context('context1')]
                fn (#[Context('context3')] FakeNoConstructorClass $obj) => $obj
            )
        );
    }

    public function testInstantiation_NoContext(): void
    {
        $container = $this->createContainer();
        $injector = $container->getInjector();
        $container->context('default')
            ->addSingletonClass(FakeNoConstructorClass::class);
        $container->push('default');

        self::assertInstanceOf(
            FakeNoConstructorClass::class,
            $injector->instantiate(FakeNoConstructorClass::class)
        );
    }

    public function testInstantiation_ContextOverride(): void
    {
        $container = $this->createContainer();
        $injector = $container->getInjector();
        $container->context('default')
            ->addSingletonInstance(Throwable::class, new Exception());
        $container->context('context1')
            ->addSingletonInstance(Throwable::class, new Exception())
            ->addSingletonInstance(RuntimeException::class, $runtime1 = new RuntimeException());
        $container->context('context2')
            ->addSingletonInstance(Throwable::class, new Exception());
        $container->context('context3')
            ->addSingletonInstance(Throwable::class, $throwable3 = new Exception());
        $container->context('context4');

        self::assertSame($throwable3, $injector->instantiate(FakeContextAwareClass::class)->throwable);
        self::assertSame($runtime1, $injector->instantiate(FakeContextAwareClass::class)->runtimeException);
    }

    /**
     * @return ContextContainer<Container>
     */
    private function createContainer(): ContextContainer
    {
        return new ContextContainer(fn (InjectorProvider $injector) => new Container($injector));
    }
}
