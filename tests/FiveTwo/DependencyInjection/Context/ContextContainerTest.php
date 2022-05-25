<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use FiveTwo\DependencyInjection\DependencyContainer;
use FiveTwo\DependencyInjection\DependencyInjectionException;
use FiveTwo\DependencyInjection\NoConstructorTestClass;
use PHPUnit\Framework\TestCase;

class ContextContainerTest extends TestCase
{
    public function testGet_DefaultOnly()
    {
        $container = new ContextContainer();
        $container->context()->addSingletonInstance(
            NoConstructorTestClass::class,
            $instance = new NoConstructorTestClass()
        );

        self::assertSame($instance, $container->get(NoConstructorTestClass::class));
    }

    public function testGet_ContextStackPrecedence()
    {
        $container = new ContextContainer();
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
            $container->get(NoConstructorTestClass::class, [Context::DEFAULT, 'context'])
        );
        self::assertSame($defaultInstance, $container->get(NoConstructorTestClass::class));
    }

    public function testGet_AtBottomOfStackOnly()
    {
        $container = new ContextContainer();
        $container->context()->addSingletonInstance(
            NoConstructorTestClass::class,
            $defaultInstance = new NoConstructorTestClass()
        );
        $container->context('context');

        self::assertSame($defaultInstance, $container->get(NoConstructorTestClass::class));
        self::assertSame(
            $defaultInstance,
            $container->get(NoConstructorTestClass::class, [Context::DEFAULT, 'context'])
        );
        self::expectException(DependencyInjectionException::class);
        $container->get(NoConstructorTestClass::class, ['context']);
    }

    public function testAddContext()
    {
        $container = new ContextContainer();
        $container->addContext('context', $context = new DependencyContainer());

        self::assertSame($context, $container->getContext('context'));
    }

    public function testContext_New()
    {
        $container = new ContextContainer();

        self::assertInstanceOf(DependencyContainer::class, $container->context('context'));
    }

    public function testContext_Existing()
    {
        $container = new ContextContainer();

        self::assertSame($container->context('context'), $container->context('context'));
    }

    public function testGetContext()
    {
        $container = new ContextContainer();
        $container->addContext('context', $context = new DependencyContainer());

        self::assertSame($context, $container->getContext('context'));
        self::assertSame($container->context(), $container->getContext(Context::DEFAULT));
    }

    public function testGetContext_Invalid()
    {
        $container = new ContextContainer();

        self::expectException(DependencyInjectionException::class);
        $container->getContext('context');
    }
}