<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022-2023 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Lifetime;

use PHPUnit\Framework\TestCase;
use Suhock\DependencyInjection\FakeClassNoConstructor;

/**
 * Test suite for {@see TransientStrategy}.
 */
class TransientStrategyTest extends TestCase
{
    /**
     * @return TransientStrategy<FakeClassNoConstructor>
     */
    protected function createStrategy(): TransientStrategy
    {
        /**
         * @phpstan-ignore-next-line PHPStan does not support generics on inherited constructors without repeating the
         * constructor {@link https://github.com/phpstan/phpstan/issues/3537#issuecomment-710038367}
         */
        return new TransientStrategy(FakeClassNoConstructor::class);
    }

    public function testGet_WithFactoryFunction_ReturnsValueFromFactoryFunction(): void
    {
        $strategy = $this->createStrategy();

        self::assertInstanceOf(FakeClassNoConstructor::class, $strategy->get(fn () => new FakeClassNoConstructor()));
    }

    public function testGet_WhenCalledMultipleTimes_ReturnsDistinctInstances(): void
    {
        $strategy = $this->createStrategy();

        self::assertNotSame(
            $strategy->get(fn () => new FakeClassNoConstructor()),
            $strategy->get(fn () => new FakeClassNoConstructor())
        );
    }
}
