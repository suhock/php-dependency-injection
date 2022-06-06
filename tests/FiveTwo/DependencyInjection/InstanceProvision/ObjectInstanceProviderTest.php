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

use FiveTwo\DependencyInjection\FakeClassExtendsNoConstructor;
use FiveTwo\DependencyInjection\FakeClassNoConstructor;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see ObjectInstanceProvider}.
 */
class ObjectInstanceProviderTest extends TestCase
{
    public function testGet(): void
    {
        $factory = new ObjectInstanceProvider(
            FakeClassNoConstructor::class,
            $instance = new FakeClassNoConstructor()
        );

        self::assertSame($instance, $factory->get());
    }

    public function testGet_Null(): void
    {
        $factory = new ObjectInstanceProvider(
            FakeClassNoConstructor::class,
            null
        );

        self::assertNull($factory->get());
    }

    public function testGet_SubClass(): void
    {
        $factory = new ObjectInstanceProvider(
            FakeClassNoConstructor::class,
            $instance = new FakeClassExtendsNoConstructor()
        );

        self::assertSame($instance, $factory->get());
    }

    public function testGet_WrongClass(): void
    {
        self::expectException(InstanceTypeException::class);
        new ObjectInstanceProvider(
            FakeClassExtendsNoConstructor::class,
            new FakeClassNoConstructor()
        );
    }
}
