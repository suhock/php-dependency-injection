<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Lifetime;

use FiveTwo\DependencyInjection\DependencyContainerInterface;
use FiveTwo\DependencyInjection\NoConstructorTestClass;
use PHPUnit\Framework\TestCase;

class TransientStrategyTest extends TestCase
{
    private TransientStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new TransientStrategy(NoConstructorTestClass::class);
    }

    public function testGet(): void
    {
        self::assertInstanceOf(
            NoConstructorTestClass::class,
            $this->strategy->get(fn() => new NoConstructorTestClass())
        );
    }

    public function testGet_NotSameInstance(): void
    {
        self::assertNotSame(
            $this->strategy->get(fn() => new NoConstructorTestClass()),
            $this->strategy->get(fn() => new NoConstructorTestClass())
        );
    }

    public function testGet_Null(): void
    {
        self::assertNull($this->strategy->get(fn() => null));
    }

    public function testGet_NullCalledTwice(): void
    {

        $stub = self::createMock(DependencyContainerInterface::class);
        $stub->method('has')
            ->willReturn(true);
        $stub->expects($this->exactly(2))
            ->method('get')
            ->willReturn(null);

        self::assertNull($this->strategy->get(fn() => $stub->get(NoConstructorTestClass::class)));
        self::assertNull($this->strategy->get(fn() => $stub->get(NoConstructorTestClass::class)));
    }
}
