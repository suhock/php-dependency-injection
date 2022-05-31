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
use FiveTwo\DependencyInjection\FakeNoConstructorClass;
use PHPUnit\Framework\TestCase;

class SingletonStrategyTest extends TestCase
{
    /**
     * @return SingletonStrategy<FakeNoConstructorClass>
     */
    protected function createStrategy(): SingletonStrategy
    {
        /**
         * @phpstan-ignore-next-line PHPStan does not support generics on inherited constructors without repeating the
         * constructor {@link https://github.com/phpstan/phpstan/issues/3537#issuecomment-710038367}
         */
        return new SingletonStrategy(FakeNoConstructorClass::class);
    }

    public function testGet(): void
    {
        self::assertInstanceOf(
            FakeNoConstructorClass::class,
            $this->createStrategy()->get(fn () => new FakeNoConstructorClass())
        );
    }

    public function testGet_SameInstance(): void
    {
        $strategy = $this->createStrategy();

        self::assertSame(
            $strategy->get(fn () => new FakeNoConstructorClass()),
            $strategy->get(fn () => new FakeNoConstructorClass())
        );
    }

    public function testGet_Null(): void
    {
        self::assertNull($this->createStrategy()->get(fn () => null));
    }

    public function testGet_NullCalledOnce(): void
    {
        $stub = self::createMock(ContainerInterface::class);
        $stub->method('has')
            ->willReturn(true);
        $stub->expects(self::once())
            ->method('get')
            ->willReturn(null);
        $strategy = $this->createStrategy();

        self::assertNull($strategy->get(fn () => $stub->get(FakeNoConstructorClass::class)));
        self::assertNull($strategy->get(fn () => $stub->get(FakeNoConstructorClass::class)));
    }
}
