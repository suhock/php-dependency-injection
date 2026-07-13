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
use Suhock\DependencyInjection\Fakes\FakeClassWithDependencies;
use Suhock\DependencyInjection\Fakes\FakeClassWithStringDependency;
use Suhock\DependencyInjection\Fakes\FakeConfigurator;
use Suhock\DependencyInjection\Fakes\FakeUnitEnum;
use Suhock\DependencyInjection\InstanceProvider\ObjectInstanceProvider;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;
use Suhock\DependencyInjection\Validation\ContainerValidationException;
use Suhock\DependencyInjection\Validation\ValidationIssue;
use Suhock\DependencyInjection\Validation\ValidationIssueKind;
use RuntimeException;
use Throwable;

use function array_map;

/**
 * Test suite for {@see ContainerBuilder}: the mutable configuration surface, duplicate detection, pre-build removal,
 * and the compile-validate-produce pipeline of {@see ContainerBuilder::build()}.
 */
final class ContainerBuilderTest extends AbstractDependencyInjectionTestCase
{
    public function testAdd_WithValidClass_ProductHasService(): void
    {
        // Arrange
        $builder = self::createBuilder();
        $lifetimeStrategy = new SingletonStrategy(FakeClassNoConstructor::class);
        $instanceProvider = new ObjectInstanceProvider(FakeClassNoConstructor::class, new FakeClassNoConstructor());

        // Act
        $container = $builder->add(FakeClassNoConstructor::class, $lifetimeStrategy, $instanceProvider)->build();

        // Assert
        self::assertTrue($container->has(FakeClassNoConstructor::class));
    }

    public function testAdd_WithDuplicateClass_ThrowsContainerException(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingletonClass(FakeClassNoConstructor::class);

        // Act & Assert
        $this->expectException(ContainerException::class);
        $builder->addSingletonClass(FakeClassNoConstructor::class);
    }

    public function testAddKeyed_WithValidClass_ProductHasKeyedService(): void
    {
        // Arrange & Act
        $container = self::createBuilder()
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')
            ->build();

        // Assert
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key1'));
        self::assertFalse($container->has(FakeClassNoConstructor::class));
    }

    public function testAddKeyed_WithDuplicateKey_ThrowsContainerException(): void
    {
        // Arrange
        $builder = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

        // Act & Assert
        $this->expectException(ContainerException::class);
        $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');
    }

    public function testAddKeyed_WhenUnkeyedServiceExists_AddsIndependentKeyedDescriptor(): void
    {
        // Arrange & Act
        $container = self::createBuilder()
            ->addSingletonClass(FakeClassNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')
            ->build();

        // Assert
        self::assertNotSame(
            $container->get(FakeClassNoConstructor::class),
            $container->get(FakeClassNoConstructor::class, 'key1')
        );
    }

    public function testConfigure_WithCallback_InvokesCallbackWithSelf(): void
    {
        // Arrange
        $builder = self::createBuilder();
        $configurator = $this->createMock(FakeConfigurator::class);
        $configurator->expects($this->once())
            ->method('configure')
            ->with($builder);

        // Act
        $builder->configure($configurator->configure(...));
    }

    public function testRemove_BeforeBuild_ProductLacksService(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingletonClass(FakeClassNoConstructor::class);

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class)->build();

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class));
    }

    public function testRemove_WithKey_RemovesOnlyKeyedService(): void
    {
        // Arrange
        $builder = self::createBuilder()
            ->addSingletonClass(FakeClassNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key2');

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class, 'key1')->build();

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class, 'key1'));
        self::assertTrue($container->has(FakeClassNoConstructor::class));
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key2'));
    }

    public function testRemove_WithoutKey_DoesNotRemoveKeyedService(): void
    {
        // Arrange
        $builder = self::createBuilder()
            ->addSingletonClass(FakeClassNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class)->build();

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class));
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key1'));
    }

    public function testRemove_WithEnumKey_RemovesKeyedService(): void
    {
        // Arrange
        $builder = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, FakeUnitEnum::Test);

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class, FakeUnitEnum::Test)->build();

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class, FakeUnitEnum::Test));
    }

    public function testRemove_ThenReAddUnderSameKey_ProductResolvesTheReplacement(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $builder = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class, 'key1')
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance)
            ->build();

        // Assert
        self::assertSame($expectedInstance, $container->get(FakeClassNoConstructor::class, 'key1'));
    }

    public function testBuild_WithDefectiveConfiguration_ThrowsAggregatedValidationException(): void
    {
        // Arrange: two independent defects — a missing required dependency and an unresolvable builtin parameter.
        $builder = self::createBuilder()
            ->addTransientClass(FakeClassWithDependencies::class)
            ->addTransientClass(FakeClassWithStringDependency::class);

        // Act
        try {
            $builder->build();
            self::fail('Expected ' . ContainerValidationException::class);
        } catch (ContainerValidationException $exception) {
            // Assert
            $kinds = array_map(
                static fn (ValidationIssue $issue) => $issue->kind,
                $exception->getIssues()
            );
            self::assertContains(ValidationIssueKind::MissingDependency, $kinds);
            self::assertContains(ValidationIssueKind::UnresolvableParameter, $kinds);
        }
    }

    public function testBuild_AfterFixingAFailedBuild_Succeeds(): void
    {
        // Arrange: FakeClassWithDependencies requires Throwable and RuntimeException.
        $builder = self::createBuilder()->addTransientClass(FakeClassWithDependencies::class);

        try {
            $builder->build();
            self::fail('Expected ' . ContainerValidationException::class);
        } catch (ContainerValidationException) {
            // The builder must remain fully usable after the failed build.
        }

        // Act
        $container = $builder
            ->addSingletonFactory(Throwable::class, static fn (): RuntimeException => new RuntimeException())
            ->addSingletonFactory(RuntimeException::class, static fn (): RuntimeException => new RuntimeException())
            ->build();

        // Assert
        self::assertInstanceOf(
            FakeClassWithDependencies::class,
            $container->get(FakeClassWithDependencies::class)
        );
    }

    public function testBuild_CalledTwice_ProducesIndependentProducts(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingletonClass(FakeClassNoConstructor::class);

        // Act
        $first = $builder->build();
        $second = $builder->build();

        // Assert: each product caches its own singleton.
        self::assertNotSame($first, $second);
        self::assertNotSame(
            $first->get(FakeClassNoConstructor::class),
            $second->get(FakeClassNoConstructor::class)
        );
    }

    public function testBuild_AfterBuild_AddedServicesDoNotAffectEarlierProducts(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingletonClass(FakeClassNoConstructor::class);

        // Act
        $first = $builder->build();
        $second = $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')->build();

        // Assert
        self::assertFalse($first->has(FakeClassNoConstructor::class, 'key1'));
        self::assertTrue($second->has(FakeClassNoConstructor::class, 'key1'));
    }
}
