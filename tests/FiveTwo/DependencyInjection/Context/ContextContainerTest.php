<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use FiveTwo\DependencyInjection\Container;
use FiveTwo\DependencyInjection\InjectorProvider;
use FiveTwo\DependencyInjection\NoConstructorTestClass;
use PHPUnit\Framework\TestCase;

class ContextContainerTest extends TestCase
{
    public function testGet_DefaultOnly(): void
    {
        $container = $this->createContainer();
        $container->context()->addSingletonInstance(
            NoConstructorTestClass::class,
            $instance = new NoConstructorTestClass()
        );

        self::assertSame($instance, $container->get(NoConstructorTestClass::class));
    }

    public function testGet_ContextStackPrecedence(): void
    {
        $container = $this->createContainer();
        $container->context()->addSingletonInstance(
            NoConstructorTestClass::class,
            $defaultInstance = new NoConstructorTestClass()
        );
        $container->context('context')->addSingletonInstance(
            NoConstructorTestClass::class,
            $contextInstance = new NoConstructorTestClass()
        );

        self::assertSame(
            $contextInstance,
            $container->push('context')->get(NoConstructorTestClass::class)
        );
        self::assertSame($defaultInstance, $container->resetStack()->get(NoConstructorTestClass::class));
    }

    public function testGet_AtBottomOfStackOnly(): void
    {
        $container = $this->createContainer();
        $container->context()->addSingletonInstance(
            NoConstructorTestClass::class,
            $defaultInstance = new NoConstructorTestClass()
        );
        $container->context('context');

        self::assertSame($defaultInstance, $container->get(NoConstructorTestClass::class));
        self::assertSame(
            $defaultInstance,
            $container->push('context')->get(NoConstructorTestClass::class)
        );
    }

    public function testContext_New(): void
    {
        $container = $this->createContainer();

        self::assertInstanceOf(Container::class, $container->context('context'));
    }

    public function testContext_Existing(): void
    {
        $container = $this->createContainer();

        self::assertSame($container->context('context'), $container->context('context'));
    }

    /**
     * @return ContextContainer<Container>
     */
    private function createContainer(): ContextContainer
    {
        return new ContextContainer(fn(InjectorProvider $injector) => new Container($injector));
    }
}
