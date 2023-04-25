<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provision;

use Suhock\DependencyInjection\DependencyInjectionTestCase;
use Suhock\DependencyInjection\FakeClassExtendsNoConstructor;
use Suhock\DependencyInjection\FakeClassNoConstructor;

/**
 * Test suite for {@see ObjectInstanceProvider}.
 */
class ObjectInstanceProviderTest extends DependencyInjectionTestCase
{
    public function testConstruct_WhenInstanceIsNotAnInstanceOfClass_ThrowsInstanceTypeException(): void
    {
        self::assertThrowsInstanceTypeException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            static fn () => new ObjectInstanceProvider(
                FakeClassExtendsNoConstructor::class,
                new FakeClassNoConstructor()
            )
        );
    }

    public function testGet_WithInstanceOfSameClass_ReturnsSameInstance(): void
    {
        $expectedInstance = new FakeClassNoConstructor();
        $factory = new ObjectInstanceProvider(FakeClassNoConstructor::class, $expectedInstance);

        self::assertSame($expectedInstance, $factory->get());
    }

    public function testGet_WithInstanceOfSubclass_ReturnsSameInstance(): void
    {
        $expectedInstance = new FakeClassExtendsNoConstructor();
        $factory = new ObjectInstanceProvider(FakeClassNoConstructor::class, $expectedInstance);

        self::assertSame($expectedInstance, $factory->get());
    }
}
