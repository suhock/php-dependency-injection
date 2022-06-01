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
use FiveTwo\DependencyInjection\FakeClassNoConstructor;
use FiveTwo\DependencyInjection\InjectorInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see ContextContainer}.
 */
class ContextContainerTest extends TestCase
{
    public function testGet_DefaultOnly(): void
    {
        $container = $this->createContainer();
        $container->context('default')->addSingletonInstance(
            FakeClassNoConstructor::class,
            $instance = new FakeClassNoConstructor()
        );

        self::assertSame($instance, $container->push('default')->get(FakeClassNoConstructor::class));
    }

    public function testGet_ContextStackPrecedence(): void
    {
        $container = $this->createContainer();
        $container->context('default')->addSingletonInstance(
            FakeClassNoConstructor::class,
            $defaultInstance = new FakeClassNoConstructor()
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
        self::assertSame(
            $defaultInstance,
            $container->resetStack()
                ->push('default')
                ->get(FakeClassNoConstructor::class)
        );
    }

    public function testGet_AtBottomOfStackOnly(): void
    {
        $container = $this->createContainer();
        $container->context('default')->addSingletonInstance(
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

        $container->resetStack();

        self::assertSame(
            $defaultInstance,
            $container->push('default')->get(FakeClassNoConstructor::class)
        );
    }

    public function testContext_New(): void
    {
        $container = $this->createContainer();

        self::assertInstanceOf(Container::class, $container->context('default'));
    }

    public function testContext_Existing(): void
    {
        $container = $this->createContainer();

        self::assertSame($container->context('default'), $container->context('default'));
    }

    /**
     * @return ContextContainer<Container>
     */
    private function createContainer(): ContextContainer
    {
        return new ContextContainer(fn (InjectorInterface $injector) => new Container($injector));
    }
}
