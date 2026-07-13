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
use Suhock\DependencyInjection\Fakes\FakeClassWithInjectedProperties;
use Suhock\DependencyInjection\Fakes\FakeClassWithInjectFunction;
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
    public function testGet_AutowiredClassWithInjectMethod_InvokesMethodWithResolvedArguments(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) => $builder
                ->addSingletonClass(FakeClassNoConstructor::class)
                ->addSingletonClass(FakeClassWithInjectFunction::class)
        );

        // Act: resolving the class executes its compiled plan, including the #[Inject] method edge.
        $instance = $container->get(FakeClassWithInjectFunction::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance->obj);
    }

    public function testGet_AutowiredClassWithInjectProperties_SetsPropertiesFromContainer(): void
    {
        // Arrange: the required properties resolve by type and key; the nullable one is left to its soft fallback.
        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) => $builder
                ->addSingletonClass(FakeClassNoConstructor::class)
                ->addKeyedSingletonClass(FakeClassNoConstructor::class, 'key1')
                ->addSingletonClass(FakeClassWithInjectedProperties::class)
        );

        // Act
        $instance = $container->get(FakeClassWithInjectedProperties::class);

        // Assert: public, protected, private, and keyed properties are all injected via the compiled plan.
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance->publicProperty);
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance->getProtectedProperty());
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance->getPrivateProperty());
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance->keyedProperty);
        self::assertNull($instance->optionalProperty, 'The unregistered nullable property falls back to null');
    }

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
            new ContextInstanceProvider($className, $select)
        );
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

    public function testGet_WhenFactoryBodyReenters_ThrowsWrappedCircularDependencyException(): void
    {
        // Arrange: build-time validation cannot see inside a factory body, so the runtime $resolving guard is
        // the backstop for a factory that resolves its own service.
        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) => $builder->addSingletonFactory(
                FakeClassNoConstructor::class,
                static fn (ContainerInterface $c): FakeClassNoConstructor =>
                    $c->get(FakeClassNoConstructor::class)
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

    public function testGet_WhenContextSelectorReturnsNonConformingInstance_ThrowsInstanceTypeException(): void
    {
        // Arrange: a context-derived descriptor whose selector produces the wrong type — the executor's leaf
        // type guard is the backstop. Built raw, since only auto-binding constructs these providers normally.
        $container = self::buildRawContainer([
            FakeInterfaceOne::class => self::contextDescriptor(
                FakeInterfaceOne::class,
                static fn (ResolutionContext $context): object => new FakeClassNoConstructor()
            ),
        ]);

        // Act
        $fn = static fn () => $container->get(FakeInterfaceOne::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeInterfaceOne::class,
            static fn (InstanceTypeException $exception) => self::assertInstanceOf(
                InstanceTypeException::class,
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
