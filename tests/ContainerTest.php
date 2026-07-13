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
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;

/**
 * Test suite for the built {@see Container}: resolution, keyed lookups, and the runtime backstops that survive
 * build-time validation. Configuration-surface behavior lives in {@see ContainerBuilderTest}.
 */
final class ContainerTest extends AbstractDependencyInjectionTestCase
{
    /**
     * @param class-string $className
     *
     * @return SingletonStrategy<object>
     */
    private static function singletonStrategy(string $className): SingletonStrategy
    {
        return new SingletonStrategy($className);
    }

    public function testGet_WhenClassNotInContainer_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = self::buildContainer(static fn (ContainerBuilder $builder) => null);

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testGet_WhenOpaqueProviderReenters_ThrowsWrappedCircularDependencyException(): void
    {
        // Arrange: build-time validation cannot see through a custom provider, so the runtime $resolving guard is
        // the backstop for cycles hidden inside one.
        $provider = new class () implements InstanceProviderInterface {
            public function get(ResolutionContext $context): object
            {
                return $context->container->get(FakeClassNoConstructor::class);
            }
        };

        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) => $builder->add(
                FakeClassNoConstructor::class,
                self::singletonStrategy(FakeClassNoConstructor::class),
                $provider
            )
        );

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn (CircularDependencyException $exception) => self::assertCircularDependencyException(
                FakeClassNoConstructor::class,
                $exception
            ),
            $fn
        );
    }

    public function testGet_WithRegisteredKey_ReturnsKeyedInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) =>
                $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance)
        );

        // Act
        $result = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testGet_WithUnregisteredKey_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) =>
                $builder->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor())
        );

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testGet_WithoutKeyWhenOnlyKeyedServiceExists_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) =>
                $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', new FakeClassNoConstructor())
        );

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testHas_WhenClassNotInContainer_ReturnsFalse(): void
    {
        // Arrange
        $container = self::buildContainer(static fn (ContainerBuilder $builder) => null);

        // Act
        $result = $container->has(FakeClassNoConstructor::class);

        // Assert
        self::assertFalse($result);
    }

    public function testHas_WhenValueProvidedByInstance_ReturnsTrue(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) =>
                $builder->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor())
        );

        // Act
        $result = $container->has(FakeClassNoConstructor::class);

        // Assert
        self::assertTrue($result);
    }

    public function testHas_WhenValueProvidedByFactory_ReturnsTrue(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) =>
                $builder->addSingletonFactory(FakeClassNoConstructor::class, fn () => new FakeClassNoConstructor())
        );

        // Act
        $result = $container->has(FakeClassNoConstructor::class);

        // Assert
        self::assertTrue($result);
    }

    public function testHas_WithRegisteredKey_ReturnsTrue(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) =>
                $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', new FakeClassNoConstructor())
        );

        // Act
        $result = $container->has(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertTrue($result);
    }

    public function testHas_WithUnregisteredKeyWhenUnkeyedServiceExists_ReturnsFalse(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) =>
                $builder->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor())
        );

        // Act
        $result = $container->has(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertFalse($result);
    }

    public function testHas_WithoutKeyWhenOnlyKeyedServiceExists_ReturnsFalse(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) =>
                $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', new FakeClassNoConstructor())
        );

        // Act
        $result = $container->has(FakeClassNoConstructor::class);

        // Assert
        self::assertFalse($result);
    }
}
