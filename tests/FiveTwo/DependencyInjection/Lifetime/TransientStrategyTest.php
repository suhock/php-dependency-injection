<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Lifetime;

use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\FakeNoConstructorClass;
use PHPUnit\Framework\TestCase;

class TransientStrategyTest extends TestCase
{
    /**
     * @return TransientStrategy<FakeNoConstructorClass>
     */
    protected function createStrategy(): TransientStrategy
    {
        /**
         * @phpstan-ignore-next-line PHPStan does not support generics on inherited constructors without repeating the
         * constructor {@link https://github.com/phpstan/phpstan/issues/3537#issuecomment-710038367}
         */
        return new TransientStrategy(FakeNoConstructorClass::class);
    }

    public function testGet(): void
    {
        self::assertInstanceOf(
            FakeNoConstructorClass::class,
            $this->createStrategy()->get(fn () => new FakeNoConstructorClass())
        );
    }

    public function testGet_NotSameInstance(): void
    {
        $strategy = $this->createStrategy();

        self::assertNotSame(
            $strategy->get(fn () => new FakeNoConstructorClass()),
            $strategy->get(fn () => new FakeNoConstructorClass())
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

        self::assertNull($strategy->get(fn () => $stub->get(FakeNoConstructorClass::class)));
        self::assertNull($strategy->get(fn () => $stub->get(FakeNoConstructorClass::class)));
    }
}
