<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\FakeClassExtendsNoConstructor;
use FiveTwo\DependencyInjection\FakeClassNoConstructor;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see ImplementationInstanceFactory}.
 */
class ImplementationInstanceFactoryTest extends TestCase
{
    public function testGet(): void
    {
        $factory = new ImplementationInstanceFactory(
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class,
            $container = $this->createMock(ContainerInterface::class)
        );

        $container->method('has')->willReturn(true);
        $container->expects(self::once())
            ->method('get')
            ->with(FakeClassExtendsNoConstructor::class)
            ->willReturn(new FakeClassExtendsNoConstructor());

        self::assertInstanceOf(FakeClassExtendsNoConstructor::class, $factory->get());
    }

    public function testGet_SameClass(): void
    {
        self::expectException(ImplementationException::class);
        new ImplementationInstanceFactory(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
            $this->createMock(ContainerInterface::class)
        );
    }

    public function testGet_WrongClass(): void
    {
        self::expectException(ImplementationException::class);
        new ImplementationInstanceFactory(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            $this->createMock(ContainerInterface::class)
        );
    }
}
