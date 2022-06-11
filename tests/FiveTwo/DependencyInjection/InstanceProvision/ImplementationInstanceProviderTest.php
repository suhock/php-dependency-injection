<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\InstanceProvision;

use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\FakeClassExtendsNoConstructor;
use FiveTwo\DependencyInjection\FakeClassNoConstructor;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see ImplementationInstanceProvider}.
 */
class ImplementationInstanceProviderTest extends TestCase
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
        $this->expectExceptionObject(new ImplementationException(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
        ));
        new ImplementationInstanceProvider(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
            $this->createStub(ContainerInterface::class)
        );
    }

    public function testGet_Exception_ImplementationNotSubclass(): void
    {
        $this->expectExceptionObject(new ImplementationException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class
        ));

        new ImplementationInstanceProvider(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            $this->createStub(ContainerInterface::class)
        );
    }
}
