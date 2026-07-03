<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeUnitEnum;
use Suhock\DependencyInjection\Instantiation\ReflectionInstantiationStrategy;

/**
 * Test suite for {@see ContainerParameterResolver}.
 */
final class ContainerParameterResolverTest extends AbstractDependencyInjectionTestCase
{
    /**
     * Builds a container whose own injector resolves parameters via the {@see ContainerParameterResolver} under test,
     * wiring it through the container constructor callback exactly as production code would.
     *
     * @return array{Container, Injector}
     */
    private function createContainerAndInjector(): array
    {
        $injector = null;
        $container = new Container(
            function (ContainerInterface $container) use (&$injector): Injector {
                $resolver = new ContainerParameterResolver($container);

                return $injector = new Injector($resolver, new ReflectionInstantiationStrategy($resolver));
            }
        );

        /** @var Injector $injector populated synchronously by the constructor callback */
        return [$container, $injector];
    }

    public function testCall_ParameterHasKey_ValueInjectedFromKeyedService(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        [$container, $injector] = $this->createContainerAndInjector();
        $container->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance);

        // Act
        $result = $injector->call(fn (#[Key('key1')] FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_ParameterHasEnumKey_ValueInjectedFromKeyedService(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        [$container, $injector] = $this->createContainerAndInjector();
        $container->addKeyedSingleton(FakeClassNoConstructor::class, FakeUnitEnum::Test, $expectedInstance);

        // Act
        $result = $injector->call(fn (#[Key(FakeUnitEnum::Test)] FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_NoKey_ValueInjectedFromUnkeyedService(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        [$container, $injector] = $this->createContainerAndInjector();
        $container->addSingletonInstance(FakeClassNoConstructor::class, $expectedInstance);

        // Act
        $result = $injector->call(fn (FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_ParameterKeyNotRegistered_ThrowsParameterResolutionException(): void
    {
        // Arrange
        [, $injector] = $this->createContainerAndInjector();

        // Act
        $fn = static fn () => $injector->call(fn (#[Key('key1')] FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertThrowsParameterResolutionException('{closure}', 'obj', null, $fn);
    }
}
