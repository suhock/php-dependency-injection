<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Provision;

use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\DependencyInjectionTestCase;
use FiveTwo\DependencyInjection\FakeClassExtendsNoConstructor;
use FiveTwo\DependencyInjection\FakeClassNoConstructor;

/**
 * Test suite for {@see ImplementationInstanceProvider}.
 */
class ImplementationInstanceProviderTest extends DependencyInjectionTestCase
{
    public function testGet(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->expects(self::once())
            ->method('get')
            ->with(FakeClassExtendsNoConstructor::class)
            ->willReturn(new FakeClassExtendsNoConstructor());

        self::assertInstanceOf(
            FakeClassExtendsNoConstructor::class,
            (new ImplementationInstanceProvider(
                FakeClassNoConstructor::class,
                FakeClassExtendsNoConstructor::class,
                $container
            ))->get()
        );
    }

    public function testGet_Exception_ImplementationSameAsInterface(): void
    {
        self::assertImplementationException(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
            fn () => new ImplementationInstanceProvider(
                FakeClassNoConstructor::class,
                FakeClassNoConstructor::class,
                $this->createStub(ContainerInterface::class)
            )
        );
    }

    public function testGet_Exception_ImplementationNotSubclass(): void
    {
        self::assertImplementationException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            fn () => new ImplementationInstanceProvider(
                FakeClassExtendsNoConstructor::class,
                FakeClassNoConstructor::class,
                $this->createStub(ContainerInterface::class)
            )
        );
    }
}
