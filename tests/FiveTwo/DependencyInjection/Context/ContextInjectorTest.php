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
use FiveTwo\DependencyInjection\FakeClassNoConstructor;
use FiveTwo\DependencyInjection\FakeClassUsingContexts;
use FiveTwo\DependencyInjection\InjectorInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Test suite for {@see ContextInjector}.
 */
class ContextInjectorTest extends TestCase
{
    public function testCall_NoContext(): void
    {
        $container = $this->createContainer();
        $injector = $container->getInjector();
        $container->context('default')
            ->addSingletonInstance(FakeClassNoConstructor::class, $instance = new FakeClassNoConstructor());
        $container->push('default');

        self::assertSame($instance, $injector->call(fn (FakeClassNoConstructor $obj) => $obj));
    }

    public function testCall_ContextOverride(): void
    {
        $container = $this->createContainer();
        $injector = $container->getInjector();

        $container->context('default')
            ->addSingletonInstance(FakeClassNoConstructor::class, $instance0 = new FakeClassNoConstructor());
        $container->context('context1')
            ->addSingletonInstance(FakeClassNoConstructor::class, $instance1 = new FakeClassNoConstructor());
        $container->context('context2')
            ->addSingletonInstance(FakeClassNoConstructor::class, $instance2 = new FakeClassNoConstructor());
        $container->context('context3');

        $container->push('default');
        self::assertSame(
            $instance0,
            $injector->call(fn (FakeClassNoConstructor $obj) => $obj)
        );
        self::assertSame(
            $instance1,
            $injector->call(
                #[Context('context1')]
                fn (FakeClassNoConstructor $obj) => $obj
            )
        );
        self::assertSame(
            $instance2,
            $injector->call(
                #[Context('context1')]
                fn (#[Context('context2')] FakeClassNoConstructor $obj) => $obj
            )
        );
        self::assertSame(
            $instance1,
            $injector->call(
                #[Context('context1')]
                fn (#[Context('context3')] FakeClassNoConstructor $obj) => $obj
            )
        );
    }

    public function testInstantiation_NoContext(): void
    {
        $container = $this->createContainer();
        $injector = $container->getInjector();
        $container->context('default')
            ->addSingletonClass(FakeClassNoConstructor::class);
        $container->push('default');

        self::assertInstanceOf(
            FakeClassNoConstructor::class,
            $injector->instantiate(FakeClassNoConstructor::class)
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

        self::assertSame($throwable3, $injector->instantiate(FakeClassUsingContexts::class)->throwable);
        self::assertSame($runtime1, $injector->instantiate(FakeClassUsingContexts::class)->runtimeException);
    }

    /**
     * @return ContextContainer<Container>
     */
    private function createContainer(): ContextContainer
    {
        return new ContextContainer(fn (InjectorInterface $injector) => new Container($injector));
    }
}
