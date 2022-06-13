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

use FiveTwo\DependencyInjection\Container;
use FiveTwo\DependencyInjection\DependencyInjectionException;
use FiveTwo\DependencyInjection\DependencyInjectionTestCase;
use FiveTwo\DependencyInjection\FakeClassNoConstructor;
use FiveTwo\DependencyInjection\InjectorInterface;

/**
 * Test suite for {@see ContextContainer}.
 */
class ContextContainerTest extends DependencyInjectionTestCase
{
    /**
     * @return ContextContainer<Container>
     */
    private function createContainer(): ContextContainer
    {
        return new ContextContainer(fn (InjectorInterface $injector) => new Container($injector));
    }

    public function testGet_DefaultOnly(): void
    {
        $container = $this->createContainer();
        $container->context('default')
            ->addSingletonInstance(
                FakeClassNoConstructor::class,
                $instance = new FakeClassNoConstructor()
            );

        self::assertSame($instance, $container->push('default')->get(FakeClassNoConstructor::class));
    }

    public function testGet_TopOfStackTakesPrecedence(): void
    {
        $container = $this->createContainer();
        $container->context('default')->addSingletonInstance(
            FakeClassNoConstructor::class,
            new FakeClassNoConstructor()
        );
        $container->context('new')->addSingletonInstance(
            FakeClassNoConstructor::class,
            $contextInstance = new FakeClassNoConstructor()
        );

        self::assertSame(
            $contextInstance,
            $container
                ->push('default')
                ->push('new')
                ->get(FakeClassNoConstructor::class)
        );
    }

    public function testGet_BottomOfStackUsedIfNotOnTop(): void
    {
        $container = $this->createContainer();
        $container->context('default')
            ->addSingletonInstance(
                FakeClassNoConstructor::class,
                $defaultInstance = new FakeClassNoConstructor()
            );
        $container->context('new');

        self::assertSame(
            $defaultInstance,
            $container
                ->push('default')
                ->push('new')
                ->get(FakeClassNoConstructor::class)
        );
    }

    public function testGet_EmptyStack(): void
    {
        $container = $this->createContainer();
        $container->context('default')->addSingletonInstance(
            FakeClassNoConstructor::class,
            new FakeClassNoConstructor()
        );

        self::assertUnresolvedClassException(
            FakeClassNoConstructor::class,
            fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testContext_RepeatCallReturnsSameInstance(): void
    {
        $container = $this->createContainer();

        self::assertSame($container->context('default'), $container->context('default'));
    }

    public function testContext_DifferentNamesReturnDistinctInstances(): void
    {
        $container = $this->createContainer();

        self::assertNotSame($container->context('default'), $container->context('new'));
    }

    public function testPush(): void
    {
        $container = $this->createContainer();
        $container->push('test1');
        $container->push('test2');

        self::assertEquals(['test1', 'test2'], $container->getStack());
    }

    public function testPop(): void
    {
        $container = $this->createContainer();
        $container->push('test1');
        $container->pop();

        self::assertEmpty($container->getStack());
    }

    public function testPop_Exception_StackIsEmpty(): void
    {
        $container = $this->createContainer();

        $this->expectException(DependencyInjectionException::class);
        $container->pop();
    }

    public function testResetStack(): void
    {
        $container = $this->createContainer();
        $container->push('test');
        $container->resetStack();

        self::assertEmpty($container->getStack());
    }
}
