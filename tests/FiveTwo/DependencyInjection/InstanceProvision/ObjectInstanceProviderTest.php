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

use FiveTwo\DependencyInjection\DependencyInjectionTestCase;
use FiveTwo\DependencyInjection\FakeClassExtendsNoConstructor;
use FiveTwo\DependencyInjection\FakeClassNoConstructor;

/**
 * Test suite for {@see ObjectInstanceProvider}.
 */
class ObjectInstanceProviderTest extends DependencyInjectionTestCase
{
    public function testGet_InstanceIsSameAsClass(): void
    {
        $instance = new FakeClassNoConstructor();

        self::assertSame(
            $instance,
            (new ObjectInstanceProvider(FakeClassNoConstructor::class, $instance))->get()
        );
    }

    public function testGet_InstanceIsSubclass(): void
    {
        $instance = new FakeClassExtendsNoConstructor();

        self::assertSame(
            $instance,
            (new ObjectInstanceProvider(FakeClassNoConstructor::class, $instance))->get()
        );
    }

    public function testGet_InstanceIsNull(): void
    {
        $factory = new ObjectInstanceProvider(
            FakeClassNoConstructor::class,
            null
        );

        self::assertNull($factory->get());
    }

    public function testGet_Exception_InstanceIsWrongClass(): void
    {
        self::assertInstanceTypeException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            fn() => new ObjectInstanceProvider(FakeClassExtendsNoConstructor::class, new FakeClassNoConstructor())
        );
    }
}
