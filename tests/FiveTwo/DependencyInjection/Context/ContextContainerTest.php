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
use FiveTwo\DependencyInjection\ContainerException;
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

    public function testGet_ValueInOnePushedContext_ReturnsValueFromContext(): void
    {
        $container = $this->createContainer();
        $container->context('default')
            ->addSingletonInstance(
                FakeClassNoConstructor::class,
                $instance = new FakeClassNoConstructor()
            );
        $container->push('default');

        self::assertSame($instance, $container->get(FakeClassNoConstructor::class));
    }

    public function testGet_ValueInTwoPushedContexts_ReturnsValueFromTopOfStack(): void
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

    public function testGet_ValueAtBottomOfStackOnly_ReturnsValueFromBottomOfStack(): void
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

    public function testGet_EmptyStack_ThrowsUnresolvedClassException(): void
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

    public function testContext_RepeatedCallsForSameName_ReturnsSameInstance(): void
    {
        $container = $this->createContainer();

        self::assertSame($container->context('default'), $container->context('default'));
    }

    public function testContext_CallsForDifferentNames_ReturnDistinctInstances(): void
    {
        $container = $this->createContainer();

        self::assertNotSame($container->context('default'), $container->context('new'));
    }

    public function testPush_OnEmptyStack_PushesCorrectValue(): void
    {
        $container = $this->createContainer();
        $container->push('test1');

        self::assertEquals('test1', $container->pop());
    }

    public function testPush_OnNonEmptyStack_PushesCorrectValue(): void
    {
        $container = $this->createContainer();
        $container->push('test1');
        $container->push('test2');

        self::assertEquals('test2', $container->pop());
    }

    public function testPop_WithOneItem_ResultsInEmptyStack(): void
    {
        $container = $this->createContainer();
        $container->push('test1');
        $container->pop();

        self::assertSame(0, $container->getStackHeight());
    }

    public function testPop_WithTwoItems_ResultsInStackWithOneItem(): void
    {
        $container = $this->createContainer();
        $container->push('test1');
        $container->push('test2');
        $container->pop();

        self::assertSame(1, $container->getStackHeight());
    }

    public function testPop_WithTwoItems_BottomItemLeftInStack(): void
    {
        $container = $this->createContainer();
        $container->push('test1');
        $container->push('test2');
        $container->pop();

        self::assertSame('test1', $container->pop());
    }

    public function testPop_WithEmptyStack_ThrowsContainerException(): void
    {
        $container = $this->createContainer();

        $this->expectException(ContainerException::class);
        $container->pop();
    }

    public function testResetStack_WithStack_ResultsInEmptyStack(): void
    {
        $container = $this->createContainer();
        $container->push('test1');
        $container->resetStack();

        self::assertSame(0, $container->getStackHeight());
    }
}
