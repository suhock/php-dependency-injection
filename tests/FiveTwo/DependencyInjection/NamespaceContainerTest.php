<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use DateTime;
use PHPUnit\Framework\TestCase;

class NamespaceContainerTest extends TestCase
{
    public function testGet(): void
    {
        $injector = self::createMock(DependencyInjectorInterface::class);
        $injector->expects($this->once())
            ->method('instantiate')
            ->with(NoConstructorTestClass::class)
            ->willReturn(new NoConstructorTestClass());

        $container = new NamespaceContainer(
            __NAMESPACE__,
            /** @param class-string $className */
            fn(string $className) => $injector->instantiate($className)
        );

        self::assertInstanceOf(NoConstructorTestClass::class, $container->get(NoConstructorTestClass::class));
    }

    public function testGet_ClassNotInNamespace(): void
    {
        $container = new NamespaceContainer(
            __NAMESPACE__,
            fn() => null
        );

        self::expectException(UnresolvedClassException::class);
        $container->get(DateTime::class);
    }

    public function testHas(): void
    {
        $container = new NamespaceContainer(
            __NAMESPACE__,
            fn() => null
        );

        self::assertTrue($container->has(NoConstructorTestClass::class));
        self::assertFalse($container->has(DateTime::class));
    }

    public function testHas_Root(): void
    {
        $container = new NamespaceContainer(
            '',
            fn() => null
        );

        self::assertTrue($container->has(NoConstructorTestClass::class));
        self::assertTrue($container->has(DateTime::class));
    }
}
