<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\InjectorInterface;
use FiveTwo\DependencyInjection\NoConstructorTestClass;
use PHPUnit\Framework\TestCase;

class ClassInstanceFactoryTest extends TestCase
{
    public function testGet(): void
    {
        $factory = new ClassInstanceFactory(
            NoConstructorTestClass::class,
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects($this->once())
            ->method('instantiate')
            ->willReturn(new NoConstructorTestClass());

        self::assertInstanceOf(NoConstructorTestClass::class, $factory->get());
    }
}
