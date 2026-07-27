<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Closure;
use Suhock\DependencyInjection\Builder\Descriptor;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\InstanceProvider\ContextInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\InstanceTypeException;
use Suhock\DependencyInjection\Lifetime\TransientStrategy;

/**
 * Test suite for the built {@see Container}: resolution, keyed lookups, and the runtime backstops that survive
 * build-time validation. Configuration-surface behavior lives in {@see ContainerBuilderTest}.
 */
final class ContainerTest extends AbstractDependencyInjectionTestCase
{
    /**
     * @param class-string $className
     * @param Closure(ResolutionContext):object $select
     *
     * @return Descriptor<object>
     */
    private static function contextDescriptor(string $className, Closure $select): Descriptor
    {
        return new Descriptor(
            $className,
            new TransientStrategy($className),
            new ContextInstanceProvider($className, $select),
        );
    }

    public function testGet_WhenClassNotInContainer_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = self::buildContainer(static fn(ContainerBuilder $builder) => null);

        // Act
        $fn = static fn() => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testGet_WithMultipleSelfParameters_PassesTheSameInstanceToEach(): void
    {
        // Arrange: the self parameter is the instance the descriptor would have produced, so there is one per
        // resolution however many parameters observe it.
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingletonFactory(
                FakeClassNoConstructor::class,
                static function (
                    FakeClassNoConstructor $first,
                    FakeClassNoConstructor $second,
                ): FakeClassNoConstructor {
                    $first->string = 'configured';

                    return $second;
                },
            ),
        );

        // Act
        $result = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame('configured', $result->string);
    }

    public function testGet_WhenSelfFactoryReturnsAnotherInstance_ReturnsTheFactorysResult(): void
    {
        // Arrange: the return value is the product, so a factory may replace the instance it was handed.
        $replacement = new FakeClassNoConstructor();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingletonFactory(
                FakeClassNoConstructor::class,
                static fn(FakeClassNoConstructor $self): FakeClassNoConstructor => $replacement,
            ),
        );

        // Act
        $result = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($replacement, $result);
    }

    public function testGet_WithSelfParameter_DoesNotTripTheCircularDependencyGuard(): void
    {
        // Arrange: the self parameter is constructed directly, so the descriptor is never re-entered.
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder
                ->addTransient(FakeClassNoConstructor::class)
                ->addTransientFactory(
                    FakeClassWithConstructor::class,
                    static fn(FakeClassWithConstructor $self): FakeClassWithConstructor => $self,
                ),
        );

        // Act
        $result = $container->get(FakeClassWithConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassWithConstructor::class, $result);
    }

    public function testGet_WhenFactoryBodyReenters_ThrowsWrappedCircularDependencyException(): void
    {
        // Arrange: build-time validation cannot see inside a factory body, so the runtime $resolving guard is
        // the backstop for a factory that resolves its own service.
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingletonFactory(
                FakeClassNoConstructor::class,
                static fn(ContainerInterface $c): FakeClassNoConstructor
                    => $c->get(FakeClassNoConstructor::class),
            ),
        );

        // Act
        $fn = static fn() => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn(CircularDependencyException $exception) => self::assertCircularDependencyException(
                FakeClassNoConstructor::class,
                $exception,
            ),
            $fn,
        );
    }

    public function testGet_WhenContextSelectorReturnsNonConformingInstance_ThrowsInstanceTypeException(): void
    {
        // Arrange: a context-derived descriptor whose selector produces the wrong type; the executor's leaf
        // type guard is the backstop. Built raw, since only auto-binding constructs these providers normally.
        $container = self::buildRawContainer([
            FakeInterfaceOne::class => self::contextDescriptor(
                FakeInterfaceOne::class,
                static fn(ResolutionContext $context): object => new FakeClassNoConstructor(),
            ),
        ]);

        // Act
        $fn = static fn() => $container->get(FakeInterfaceOne::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeInterfaceOne::class,
            static fn(InstanceTypeException $exception) => self::assertInstanceOf(
                InstanceTypeException::class,
                $exception,
            ),
            $fn,
        );
    }

    public function testGet_WithRegisteredKey_ReturnsKeyedInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder)
                => $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance),
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
            static fn(ContainerBuilder $builder)
                => $builder->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor()),
        );

        // Act
        $fn = static fn() => $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testGet_WithoutKeyWhenOnlyKeyedServiceExists_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder)
                => $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', new FakeClassNoConstructor()),
        );

        // Act
        $fn = static fn() => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testHas_WhenClassNotInContainer_ReturnsFalse(): void
    {
        // Arrange
        $container = self::buildContainer(static fn(ContainerBuilder $builder) => null);

        // Act
        $result = $container->has(FakeClassNoConstructor::class);

        // Assert
        self::assertFalse($result);
    }

    public function testHas_WhenValueProvidedByInstance_ReturnsTrue(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder)
                => $builder->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor()),
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
            static fn(ContainerBuilder $builder)
                => $builder->addSingletonFactory(FakeClassNoConstructor::class, fn() => new FakeClassNoConstructor()),
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
            static fn(ContainerBuilder $builder)
                => $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', new FakeClassNoConstructor()),
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
            static fn(ContainerBuilder $builder)
                => $builder->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor()),
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
            static fn(ContainerBuilder $builder)
                => $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', new FakeClassNoConstructor()),
        );

        // Act
        $result = $container->has(FakeClassNoConstructor::class);

        // Assert
        self::assertFalse($result);
    }

    public function testGetConcreteClassName_WithAutowiredClass_ReturnsTheClass(): void
    {
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addTransientClass(FakeClassNoConstructor::class),
        );

        self::assertSame(
            FakeClassNoConstructor::class,
            $container->getConcreteClassName(FakeClassNoConstructor::class),
        );
    }

    public function testGetConcreteClassName_WithImplementation_ReturnsConcreteTarget(): void
    {
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addTransientClass(FakeClassNoConstructor::class)
                ->addTransientClass(FakeClassWithConstructor::class)
                ->addTransientImplementation(FakeInterfaceOne::class, FakeClassWithConstructor::class),
        );

        self::assertSame(FakeClassWithConstructor::class, $container->getConcreteClassName(FakeInterfaceOne::class));
    }

    public function testGetConcreteClassName_WithFactoryConcreteReturnType_ReturnsIt(): void
    {
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addTransientFactory(
                FakeInterfaceOne::class,
                static fn(): FakeClassWithConstructor => new FakeClassWithConstructor(new FakeClassNoConstructor()),
            ),
        );

        self::assertSame(FakeClassWithConstructor::class, $container->getConcreteClassName(FakeInterfaceOne::class));
    }

    public function testGetConcreteClassName_WithFactoryInterfaceReturnType_ReturnsNull(): void
    {
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addTransientFactory(
                FakeInterfaceOne::class,
                static fn(): FakeInterfaceOne => new FakeClassWithConstructor(new FakeClassNoConstructor()),
            ),
        );

        self::assertNull($container->getConcreteClassName(FakeInterfaceOne::class));
    }

    public function testGetConcreteClassName_WithHeldInstance_ReturnsTheInstanceClass(): void
    {
        $instance = new FakeClassWithConstructor(new FakeClassNoConstructor());
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingletonInstance(FakeInterfaceOne::class, $instance),
        );

        self::assertSame(FakeClassWithConstructor::class, $container->getConcreteClassName(FakeInterfaceOne::class));
    }

    public function testGetConcreteClassName_WhenNotRegistered_ReturnsNull(): void
    {
        $container = self::buildContainer(static fn(ContainerBuilder $builder) => null);

        self::assertNull($container->getConcreteClassName(FakeClassNoConstructor::class));
    }
}
