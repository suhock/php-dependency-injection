<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\FakeNoConstructorClass;
use FiveTwo\DependencyInjection\Injector;
use FiveTwo\DependencyInjection\InjectorInterface;
use PHPUnit\Framework\TestCase;

class ClassInstanceFactoryTest extends TestCase
{
    public function testGet_NoMutator(): void
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

    public function testGet_WithMutator(): void
    {
        self::assertSame('test', (new ClassInstanceFactory(
            FakeNoConstructorClass::class,
            new Injector(self::createMock(ContainerInterface::class)),
            function (FakeNoConstructorClass $obj) {
                $obj->string = 'test';
            }
        ))->get()->string);
    }
}
