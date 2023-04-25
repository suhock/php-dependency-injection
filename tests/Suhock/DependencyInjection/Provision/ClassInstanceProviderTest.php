<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022-2023 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provision;

use Suhock\DependencyInjection\ContainerInjector;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\DependencyInjectionTestCase;
use Suhock\DependencyInjection\FakeClassNoConstructor;
use Suhock\DependencyInjection\InjectorInterface;

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
            new ContainerInjector($this->createMock(ContainerInterface::class)),
            function (FakeClassNoConstructor $obj) {
                $obj->string = 'test';
            }
        );

        self::assertSame('test', $factory->get()->string);
    }
}
