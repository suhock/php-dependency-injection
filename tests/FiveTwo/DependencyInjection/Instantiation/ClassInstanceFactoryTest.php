<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\FakeNoConstructorClass;
use FiveTwo\DependencyInjection\InjectorInterface;
use PHPUnit\Framework\TestCase;

class ClassInstanceFactoryTest extends TestCase
{
    public function testGet(): void
    {
        $factory = new ClassInstanceFactory(
            FakeNoConstructorClass::class,
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects(self::once())
            ->method('instantiate')
            ->willReturn(new FakeNoConstructorClass());

        self::assertInstanceOf(FakeNoConstructorClass::class, $factory->get());
    }
}
