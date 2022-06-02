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
use FiveTwo\DependencyInjection\FakeClassNoConstructor;
use FiveTwo\DependencyInjection\Injector;
use FiveTwo\DependencyInjection\InjectorInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see ClassInstaceProvider}.
 */
class ClassInstanceProviderTest extends TestCase
{
    public function testGet_NoMutator(): void
    {
        $factory = new ClassInstaceProvider(
            FakeClassNoConstructor::class,
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects(self::once())
            ->method('instantiate')
            ->willReturn(new FakeClassNoConstructor());

        self::assertInstanceOf(FakeClassNoConstructor::class, $factory->get());
    }

    public function testGet_WithMutator(): void
    {
        self::assertSame('test', (new ClassInstaceProvider(
            FakeClassNoConstructor::class,
            new Injector(self::createMock(ContainerInterface::class)),
            function (FakeClassNoConstructor $obj) {
                $obj->string = 'test';
            }
        ))->get()->string);
    }
}
