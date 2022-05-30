<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use FiveTwo\DependencyInjection\Container;
use FiveTwo\DependencyInjection\FakeNoConstructorClass;
use FiveTwo\DependencyInjection\InjectorProvider;
use PHPUnit\Framework\TestCase;

class ContextContainerTest extends TestCase
{
    public function testGet_DefaultOnly(): void
    {
        $container = $this->createContainer();
        $container->context('default')->addSingletonInstance(
            FakeNoConstructorClass::class,
            $instance = new FakeNoConstructorClass()
        );

        self::assertSame($instance, $container->push('default')->get(FakeNoConstructorClass::class));
    }

    public function testGet_ContextStackPrecedence(): void
    {
        $container = $this->createContainer();
        $container->context('default')->addSingletonInstance(
            FakeNoConstructorClass::class,
            $defaultInstance = new FakeNoConstructorClass()
        );
        $container->context('new')->addSingletonInstance(
            FakeNoConstructorClass::class,
            $contextInstance = new FakeNoConstructorClass()
        );

        self::assertSame(
            $contextInstance,
            $container
                ->push('default')
                ->push('new')
                ->get(FakeNoConstructorClass::class)
        );
        self::assertSame(
            $defaultInstance,
            $container->resetStack()
                ->push('default')
                ->get(FakeNoConstructorClass::class)
        );
    }

    public function testGet_AtBottomOfStackOnly(): void
    {
        $container = $this->createContainer();
        $container->context('default')->addSingletonInstance(
            FakeNoConstructorClass::class,
            $defaultInstance = new FakeNoConstructorClass()
        );
        $container->context('new');

        self::assertSame(
            $defaultInstance,
            $container
                ->push('default')
                ->push('new')
                ->get(FakeNoConstructorClass::class)
        );

        $container->resetStack();

        self::assertSame(
            $defaultInstance,
            $container->push('default')->get(FakeNoConstructorClass::class)
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
        return new ContextContainer(fn (InjectorProvider $injector) => new Container($injector));
    }
}
