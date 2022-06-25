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
use FiveTwo\DependencyInjection\FakeClassNoConstructor;
use FiveTwo\DependencyInjection\Injector;
use FiveTwo\DependencyInjection\InjectorInterface;

/**
 * Test suite for {@see ClassInstanceProvider}.
 */
class ClassInstanceProviderTest extends DependencyInjectionTestCase
{
    public function testGet_WithClassName_ReturnsValueInstantiatedByInjector(): void
    {
        $factory = new ClassInstanceProvider(
            FakeClassNoConstructor::class,
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects(self::once())
            ->method('instantiate')
            ->willReturn(new FakeClassNoConstructor());

        self::assertInstanceOf(FakeClassNoConstructor::class, $factory->get());
    }

    public function testGet_WithMutatorFunction_ReturnsValueMutatedByFunction(): void
    {
        $factory = new ClassInstanceProvider(
            FakeClassNoConstructor::class,
            new Injector($this->createMock(ContainerInterface::class)),
            function (FakeClassNoConstructor $obj) {
                $obj->string = 'test';
            }
        );

        self::assertSame('test', $factory->get()->string);
    }
}
