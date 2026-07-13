<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use Suhock\DependencyInjection\AbstractDependencyInjectionTestCase;
use Suhock\DependencyInjection\Container;
use Suhock\DependencyInjection\ContainerBuilder;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeUnitEnum;
use Suhock\DependencyInjection\Injection\InjectAttributeMemberInjector;
use Suhock\DependencyInjection\Injector;
use Suhock\DependencyInjection\Instantiation\ReflectionInstantiationStrategy;
use Suhock\DependencyInjection\Key;
use ReflectionParameter;

/**
 * Test suite for {@see ContainerParameterResolver}.
 */
final class ContainerParameterResolverTest extends AbstractDependencyInjectionTestCase
{
    /**
     * Builds a container, then constructs an injector whose {@see ContainerParameterResolver} under test resolves
     * parameters from it — the standalone-injector wiring production code uses.
     *
     * @param callable(ContainerBuilder):mixed $configure
     *
     * @return array{Container, Injector}
     */
    private function createContainerAndInjector(?callable $configure = null): array
    {
        $builder = new ContainerBuilder();

        if ($configure !== null) {
            $configure($builder);
        }

        $container = $builder->build();
        $resolver = new ContainerParameterResolver($container);
        $injector = new Injector(
            $resolver,
            new ReflectionInstantiationStrategy($resolver),
            new InjectAttributeMemberInjector($resolver)
        );

        return [$container, $injector];
    }

    public function testCall_ParameterHasKey_ValueInjectedFromKeyedService(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        [$container, $injector] = $this->createContainerAndInjector(
            static fn (ContainerBuilder $builder) =>
                $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance)
        );

        // Act
        $result = $injector->call(fn (#[Key('key1')] FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_ParameterHasEnumKey_ValueInjectedFromKeyedService(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        [$container, $injector] = $this->createContainerAndInjector(
            static fn (ContainerBuilder $builder) =>
                $builder->addKeyedSingleton(FakeClassNoConstructor::class, FakeUnitEnum::Test, $expectedInstance)
        );

        // Act
        $result = $injector->call(fn (#[Key(FakeUnitEnum::Test)] FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_NoKey_ValueInjectedFromUnkeyedService(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        [$container, $injector] = $this->createContainerAndInjector(
            static fn (ContainerBuilder $builder) =>
                $builder->addSingletonInstance(FakeClassNoConstructor::class, $expectedInstance)
        );

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

    public function testHasDependency_WhenContainerHasCandidate_ReturnsTrue(): void
    {
        // Arrange
        [$container] = $this->createContainerAndInjector(
            static fn (ContainerBuilder $builder) =>
                $builder->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor())
        );
        $resolver = new ContainerParameterResolver($container);
        $dependency = new ResolvableDependency('param', [[FakeClassNoConstructor::class]]);

        // Act & Assert
        self::assertTrue($resolver->hasDependency($dependency));
    }

    public function testHasDependency_WhenNoCandidateRegistered_ReturnsFalse(): void
    {
        // Arrange
        [$container] = $this->createContainerAndInjector();
        $resolver = new ContainerParameterResolver($container);
        $dependency = new ResolvableDependency('param', [[FakeClassNoConstructor::class]]);

        // Act & Assert
        self::assertFalse($resolver->hasDependency($dependency));
    }

    public function testResolveDependency_WhenContainerHasCandidate_ReturnsInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        [$container] = $this->createContainerAndInjector(
            static fn (ContainerBuilder $builder) =>
                $builder->addSingletonInstance(FakeClassNoConstructor::class, $expectedInstance)
        );
        $resolver = new ContainerParameterResolver($container);
        $dependency = new ResolvableDependency('param', [[FakeClassNoConstructor::class]]);

        // Act
        $result = $resolver->resolveDependency($dependency);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testResolveDependency_WhenNoCandidateRegistered_ThrowsClassNotFoundException(): void
    {
        // Arrange
        [$container] = $this->createContainerAndInjector();
        $resolver = new ContainerParameterResolver($container);
        $dependency = new ResolvableDependency('param', [[FakeClassNoConstructor::class]]);

        // Act
        $fn = static fn () => $resolver->resolveDependency($dependency);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testGetResolvableDependency_PlainTypedParameter_ReturnsDependency(): void
    {
        // Arrange
        $resolver = new ContainerParameterResolver(self::createStub(ContainerInterface::class));
        $rParam = new ReflectionParameter(static fn (FakeClassNoConstructor $param) => null, 'param');

        // Act
        $dependency = $resolver->getResolvableDependency($rParam);

        // Assert
        self::assertNotNull($dependency);
        self::assertSame([[FakeClassNoConstructor::class]], $dependency->alternatives);
    }

    public function testGetResolvableDependency_NullableParameter_ReturnsNull(): void
    {
        // Arrange: a nullable parameter keeps the reflection-path fallbacks, so it is not directly resolvable.
        $resolver = new ContainerParameterResolver(self::createStub(ContainerInterface::class));
        $rParam = new ReflectionParameter(static fn (?FakeClassNoConstructor $param = null) => null, 'param');

        // Act & Assert
        self::assertNull($resolver->getResolvableDependency($rParam));
    }
}
