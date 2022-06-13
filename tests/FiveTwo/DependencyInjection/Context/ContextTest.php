<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see Context} class.
 */
class ContextTest extends TestCase
{
    public function test__construct_string(): void
    {
        $context = new Context('test');
        self::assertSame('test', $context->getName());
    }

    public function test__construct_UnitEnum(): void
    {
        $context = new Context(FakeUnitEnum::Test);
        self::assertSame('Test', $context->getName());
    }

    public function test__construct_StringBackedEnum(): void
    {
        $context = new Context(FakeStringBackedEnum::Test);
        self::assertSame('test', $context->getName());
    }

    public function test__construct_IntBackedEnum(): void
    {
        $context = new Context(FakeIntBackedEnum::Test);
        self::assertSame('Test', $context->getName());
    }
}
