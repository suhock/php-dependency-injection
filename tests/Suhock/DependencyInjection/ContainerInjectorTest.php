<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Keyed\FakeUnitEnum;
use Suhock\DependencyInjection\Keyed\Key;

/**
 * Test suite for {@see ContainerInjector}.
 */
class ContainerInjectorTest extends DependencyInjectionTestCase
{
    private function createContainer(): Container
    {
        return new Container();
    }

    public function testCall_ParameterHasKey_ValueInjectedFromKeyedRegistration(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance);
        $injector = new ContainerInjector($container);

        // Act
        $result = $injector->call(fn (#[Key('key1')] FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_FunctionHasKey_ValueInjectedFromKeyedRegistration(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance);
        $injector = new ContainerInjector($container);

        // Act
        $result = $injector->call(#[Key('key1')] fn (FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_KeyOnFunctionAndParameter_ValueInjectedFromParameterKey(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', new FakeClassNoConstructor())
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key2', $expectedInstance);
        $injector = new ContainerInjector($container);

        // Act
        $result = $injector->call(#[Key('key1')] fn (#[Key('key2')] FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_NoKey_ValueInjectedFromUnkeyedRegistration(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = $this->createContainer()
            ->addSingletonInstance(FakeClassNoConstructor::class, $expectedInstance);
        $injector = new ContainerInjector($container);

        // Act
        $result = $injector->call(fn (FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_ParameterHasEnumKey_ValueInjectedFromKeyedRegistration(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeClassNoConstructor::class, FakeUnitEnum::Test, $expectedInstance);
        $injector = new ContainerInjector($container);

        // Act
        $result = $injector->call(fn (#[Key(FakeUnitEnum::Test)] FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_ParameterKeyNotRegistered_ThrowsParameterResolutionException(): void
    {
        // Arrange
        $container = $this->createContainer();
        $injector = new ContainerInjector($container);

        // Act
        $fn = static fn () => $injector->call(fn (#[Key('key1')] FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertThrowsParameterResolutionException('{closure}', 'obj', null, $fn);
    }

    public function testInstantiate_ClassHasKey_ValueInjectedFromKeyedRegistration(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance);
        $injector = new ContainerInjector($container);

        // Act
        $result = $injector->instantiate(FakeClassWithKey::class);

        // Assert
        self::assertSame($expectedInstance, $result->obj);
    }
}
