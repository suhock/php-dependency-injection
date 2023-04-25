<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Context;

use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Suhock\DependencyInjection\Container;
use Suhock\DependencyInjection\FakeClassImplementsInterfaces;
use Suhock\DependencyInjection\FakeClassNoConstructor;
use Suhock\DependencyInjection\FakeClassWithConstructor;
use Suhock\DependencyInjection\FakeClassWithContexts;
use Suhock\DependencyInjection\FakeInterfaceOne;
use Suhock\DependencyInjection\FakeInterfaceTwo;
use Throwable;

/**
 * Test suite for {@see ContextInjector}.
 */
class ContextInjectorTest extends TestCase
{
    /**
     * @return ContextContainer<Container>
     */
    private function createContainer(): ContextContainer
    {
        return ContextContainerFactory::createForDefaultContainer();
    }

    /**
     * @return ContextContainer<Container>
     */
    private function createContainerWithDefaultContext(): ContextContainer
    {
        $container = $this->createContainer();
        $container->context('default')
            ->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor());
        $container->push('default');

        return $container;
    }

    public function testCall_FunctionWithNoContextAttributes_ValueInjectedFromPushedContext(): void
    {
        $container = $this->createContainerWithDefaultContext();
        $expectedInstance = $container->context('default')->get(FakeClassNoConstructor::class);
        $injector = new ContextContainerInjector($container);

        self::assertSame($expectedInstance, $injector->call(fn (FakeClassNoConstructor $obj) => $obj));
    }

    public function testCall_FunctionHasContext_ValueInjectedFromFunctionContext(): void
    {
        $container = $this->createContainerWithDefaultContext();
        $expectedInstance = new FakeClassNoConstructor();

        $container->context('context1')
            ->addSingletonInstance(FakeClassNoConstructor::class, $expectedInstance);

        $injector = new ContextContainerInjector($container);

        self::assertSame(
            $expectedInstance,
            $injector->call(#[Context('context1')] fn (FakeClassNoConstructor $obj) => $obj)
        );
    }

    public function testCall_ParameterHasContext_ValueInjectedFromParameterContext(): void
    {
        $container = $this->createContainerWithDefaultContext();

        $expectedInstance = new FakeClassNoConstructor();
        $container->context('context1')
            ->addSingletonInstance(FakeClassNoConstructor::class, $expectedInstance);

        $injector = new ContextContainerInjector($container);

        self::assertSame(
            $expectedInstance,
            $injector->call(fn (#[Context('context1')] FakeClassNoConstructor $obj) => $obj)
        );
    }

    public function testCall_ValueInFunctionAndParameterContexts_ValueInjectedFromParameterContext(): void
    {
        $container = $this->createContainerWithDefaultContext();

        $container->context('context1')
            ->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor());

        $expectedInstance = new FakeClassNoConstructor();
        $container->context('context2')
            ->addSingletonInstance(FakeClassNoConstructor::class, $expectedInstance);

        $injector = new ContextContainerInjector($container);

        self::assertSame(
            $expectedInstance,
            $injector->call(#[Context('context1')] fn (#[Context('context2')] FakeClassNoConstructor $obj) => $obj)
        );
    }

    public function testCall_ValueInFunctionContextOnly_ValueInjectedFromFunctionContext(): void
    {
        $container = $this->createContainerWithDefaultContext();

        $expectedInstance = new FakeClassNoConstructor();
        $container->context('context1')
            ->addSingletonInstance(FakeClassNoConstructor::class, $expectedInstance);

        $container->context('context2');

        $injector = new ContextContainerInjector($container);

        self::assertSame(
            $expectedInstance,
            $injector->call(#[Context('context1')] fn (#[Context('context2')] FakeClassNoConstructor $obj) => $obj)
        );
    }

    public function testCall_UnionType_First(): void
    {
        $container = $this->createContainer();

        $expectedInstance = new FakeClassImplementsInterfaces();
        $container->context('default')
            ->addSingletonInstance(FakeInterfaceOne::class, $expectedInstance);
        $container->push('default');

        $injector = new ContextContainerInjector($container);

        self::assertSame(
            $expectedInstance,
            $injector->call(fn (FakeInterfaceOne|string|FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_IntersectionType_First(): void
    {
        $container = $this->createContainer();

        $expectedInstance = new FakeClassImplementsInterfaces();
        $container->context('default')
            ->addSingletonInstance(FakeInterfaceOne::class, $expectedInstance);
        $container->push('default');

        $injector = new ContextContainerInjector($container);

        self::assertSame(
            $expectedInstance,
            $injector->call(fn (FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testInstantiate_ClassWithNoContextAttributes_ValueInjectedFromPushedContext(): void
    {
        $container = $this->createContainerWithDefaultContext();
        $container->context('default')->addSingletonClass(FakeClassWithConstructor::class);
        $expectedInstance = $container->context('default')->get(FakeClassNoConstructor::class);

        $injector = new ContextContainerInjector($container);

        self::assertSame($expectedInstance, $injector->instantiate(FakeClassWithConstructor::class)->obj);
    }

    public function testInstantiate_ClassWithContexts_ValuesInjectedFromCorrectContexts(): void
    {
        $container = $this->createContainer();

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
        $container->push('default');

        $injector = new ContextContainerInjector($container);

        self::assertSame($throwable3, $injector->instantiate(FakeClassWithContexts::class)->throwable);
        self::assertSame($runtime1, $injector->instantiate(FakeClassWithContexts::class)->runtimeException);
    }
}
