<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\NoConstructorTestClass;
use FiveTwo\DependencyInjection\NoConstructorTestSubClass;
use PHPUnit\Framework\TestCase;

class ObjectInstanceFactoryTest extends TestCase
{
    public function testGet(): void
    {
        $factory = new ObjectInstanceFactory(
            NoConstructorTestClass::class,
            $instance = new NoConstructorTestClass()
        );

        self::assertSame($instance, $factory->get());
    }

    public function testGet_Null(): void
    {
        $factory = new ObjectInstanceFactory(
            NoConstructorTestClass::class,
            null
        );

        self::assertNull($factory->get());
    }

    public function testGet_SubClass(): void
    {
        $factory = new ObjectInstanceFactory(
            NoConstructorTestClass::class,
            $instance = new NoConstructorTestSubClass()
        );

        self::assertSame($instance, $factory->get());
    }

    public function testGet_WrongClass(): void
    {
        self::expectException(DependencyTypeException::class);
        new ObjectInstanceFactory(
            NoConstructorTestSubClass::class,
            new NoConstructorTestClass()
        );
    }
}
