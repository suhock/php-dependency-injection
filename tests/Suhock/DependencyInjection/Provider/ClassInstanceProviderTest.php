<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provider;

use Suhock\DependencyInjection\AbstractDependencyInjectionTestCase;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Injector;
use Suhock\DependencyInjection\InjectorInterface;

/**
 * Test suite for {@see ClassInstanceProvider}.
 */
final class ClassInstanceProviderTest extends AbstractDependencyInjectionTestCase
{
    public function testGet_WithClassName_ReturnsValueInstantiatedByInjector(): void
    {
        // Arrange
        $factory = new ClassInstanceProvider(
            FakeClassNoConstructor::class,
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects($this->once())
            ->method('instantiate')
            ->willReturn(new FakeClassNoConstructor());

        // Act
        $instance = $factory->get();

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
    }

    public function testGet_WithMutatorFunction_ReturnsValueMutatedByFunction(): void
    {
        // Arrange
        $factory = new ClassInstanceProvider(
            FakeClassNoConstructor::class,
            Injector::createDefault(self::createStub(ContainerInterface::class)),
            function (FakeClassNoConstructor $obj) {
                $obj->string = 'test';
            }
        );

        // Act
        $instance = $factory->get();

        // Assert
        self::assertSame('test', $instance->string);
    }
}
