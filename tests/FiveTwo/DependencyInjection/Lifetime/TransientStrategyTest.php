<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Lifetime;

use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\FakeClassNoConstructor;
use PHPUnit\Framework\TestCase;

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

    public function testGet(): void
    {
        self::assertInstanceOf(
            FakeClassNoConstructor::class,
            $this->createStrategy()->get(fn () => new FakeClassNoConstructor())
        );
    }

    public function testGet_NotSameInstance(): void
    {
        $strategy = $this->createStrategy();

        self::assertNotSame(
            $strategy->get(fn () => new FakeClassNoConstructor()),
            $strategy->get(fn () => new FakeClassNoConstructor())
        );
    }

    public function testGet_Null(): void
    {
        self::assertNull($this->createStrategy()->get(fn () => null));
    }

    public function testGet_NullCalledTwice(): void
    {
        $stub = self::createMock(ContainerInterface::class);
        $stub->method('has')
            ->willReturn(true);
        $stub->expects(self::exactly(2))
            ->method('get')
            ->willReturn(null);
        $strategy = $this->createStrategy();

        self::assertNull($strategy->get(fn () => $stub->get(FakeClassNoConstructor::class)));
        self::assertNull($strategy->get(fn () => $stub->get(FakeClassNoConstructor::class)));
    }
}
