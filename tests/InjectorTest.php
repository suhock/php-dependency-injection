<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Exception;
use LogicException;
use PHPUnit\Framework\Attributes\RequiresPhp;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;
use Suhock\DependencyInjection\Fakes\FakeAbstractClass;
use Suhock\DependencyInjection\Fakes\FakeClassImplementsInterfaces;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithDanglingKeyProperty;
use Suhock\DependencyInjection\Fakes\FakeClassWithDependencies;
use Suhock\DependencyInjection\Fakes\FakeClassWithDnfDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithInjectedProperties;
use Suhock\DependencyInjection\Fakes\FakeClassWithInjectFunction;
use Suhock\DependencyInjection\Fakes\FakeClassWithIntersectionDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithKeyedDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithNonPublicInjectMethods;
use Suhock\DependencyInjection\Fakes\FakeClassWithScalarInjectProperty;
use Suhock\DependencyInjection\Fakes\FakeClassWithStaticInjectMethod;
use Suhock\DependencyInjection\Fakes\FakeClassWithUnionDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithVariadicConstructor;
use Suhock\DependencyInjection\Fakes\FakeContainer;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\Fakes\FakeInterfaceThree;
use Suhock\DependencyInjection\Fakes\FakeInterfaceTwo;
use Suhock\DependencyInjection\Injection\InjectAttributeMemberInjector;
use Suhock\DependencyInjection\Instantiation\InstantiationStrategyInterface;
use Suhock\DependencyInjection\Instantiation\PostInstantiationHookInterface;
use Suhock\DependencyInjection\Instantiation\ReflectionInstantiationStrategy;
use Suhock\DependencyInjection\Resolver\ContainerParameterResolver;
use Suhock\DependencyInjection\Resolver\ParameterResolverInterface;
use Suhock\DependencyInjection\Resolver\PropertyResolutionException;
use Throwable;
use UnitEnum;

/**
 * Test suite for {@see Injector}.
 */
final class InjectorTest extends AbstractDependencyInjectionTestCase
{
    /**
     * @param array<callable> $classMapping
     *
     * @phpstan-param array<class-string, callable():object> $classMapping
     */
    private function createInjector(array $classMapping = []): Injector
    {
        return Injector::createDefault(new FakeContainer($classMapping));
    }

    public function testInstantiate_WithDependenciesInContainer_ReturnsInstanceWithValuesFromContainer(): void
    {
        // Arrange
        $logicException = new LogicException();

        // Act
        $instance = $this->createInjector([
            Throwable::class => fn() => $logicException,
            LogicException::class => fn() => $logicException,
            RuntimeException::class => fn() => new RuntimeException('test'),
        ])->instantiate(FakeClassWithDependencies::class);

        // Assert
        self::assertInstanceOf(FakeClassWithDependencies::class, $instance);
        self::assertSame($logicException, $instance->throwable);
        self::assertSame('test', $instance->runtimeException->getMessage());
    }

    public function testInstantiate_WithDependenciesWithDefaultValues_ReturnsInstanceUsingDefaultValues(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act
        $instance = $injector->instantiate(Exception::class);

        // Assert
        self::assertInstanceOf(Exception::class, $instance);
    }

    public function testInstantiate_WithInvalidClass_ThrowsInjectorException(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act & Assert
        $this->expectException(InjectorException::class);
        /** @phpstan-ignore argument.type (intentionally passing a non-existent class under test) */
        $injector->instantiate('NonExistentClass');
    }

    public function testInstantiate_WithVariadicConstructor_FallsBackToReflectionPath(): void
    {
        // Arrange
        $obj = new FakeClassNoConstructor();
        $injector = $this->createInjector([FakeClassNoConstructor::class => fn() => $obj]);

        // Act
        $instance = $injector->instantiate(FakeClassWithVariadicConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassWithVariadicConstructor::class, $instance);
        self::assertSame([$obj], $instance->items);
    }

    public function testInjectMembers_WhenInjectedPropertyResolutionThrows_ThrowsPropertyResolutionException(): void
    {
        // Arrange
        $injector = $this->createInjector([
            FakeClassNoConstructor::class => fn() => throw new ClassResolutionException(FakeClassNoConstructor::class),
        ]);

        // Act & Assert
        try {
            $injector->injectMembers($injector->instantiate(FakeClassWithInjectedProperties::class));
            self::fail('Expected a PropertyResolutionException to be thrown');
        } catch (PropertyResolutionException $exception) {
            self::assertSame('publicProperty', $exception->getReflectionProperty()->getName());
            self::assertInstanceOf(ClassResolutionException::class, $exception->getConsolidatedException());
        }
    }

    public function testInstantiate_WithNonInstantiableClass_ThrowsInjectorException(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act & Assert
        $this->expectException(InjectorException::class);
        $injector->instantiate(FakeAbstractClass::class);
    }

    public function testInstantiate_WithMissingDependency_ThrowsInjectorException(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act & Assert
        // missing argument of type RuntimeException
        $this->expectException(InjectorException::class);
        $injector->instantiate(FakeClassWithDependencies::class);
    }

    public function testInstantiate_WithNamedParametersAlsoResolvableFromContainer_UsesNamedParameters(): void
    {
        // Arrange
        $injector = $this->createInjector([
            Throwable::class => fn() => new LogicException(),
            RuntimeException::class => fn() => new RuntimeException(),
        ]);
        $override = new RuntimeException();

        // Act
        $result = $injector->instantiate(FakeClassWithDependencies::class, [
            'runtimeException' => $override,
        ])->runtimeException;

        // Assert
        self::assertSame($override, $result);
    }

    public function testInstantiate_WithPositionalParametersAlsoResolvableFromContainer_UsesPositionalParameters(): void
    {
        // Arrange
        $injector = $this->createInjector([
            Throwable::class => fn() => new LogicException(),
            RuntimeException::class => fn() => new RuntimeException(),
        ]);
        $override = new RuntimeException();

        // Act
        $result = $injector->instantiate(FakeClassWithDependencies::class, [
            1 => $override,
        ])->runtimeException;

        // Assert
        self::assertSame($override, $result);
    }

    public function testInstantiate_WithNoConstructor_ReturnsInstance(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act
        $instance = $injector->instantiate(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
    }

    public function testInjectMembers_WithAutowireFunction_CallsAutowireFunction(): void
    {
        // Arrange
        $obj = new FakeClassNoConstructor();
        $injector = $this->createInjector([
            FakeClassNoConstructor::class => fn() => $obj,
        ]);

        // Act
        $instance = $injector->injectMembers($injector->instantiate(FakeClassWithInjectFunction::class));

        // Assert
        self::assertSame($obj, $instance->obj);
    }

    public function testInjectMembers_WithPublicInjectedProperty_AssignsProperty(): void
    {
        // Arrange
        $obj = new FakeClassNoConstructor();
        $injector = $this->createInjector([FakeClassNoConstructor::class => fn() => $obj]);

        // Act
        $instance = $injector->injectMembers($injector->instantiate(FakeClassWithInjectedProperties::class));

        // Assert
        self::assertSame($obj, $instance->publicProperty);
    }

    public function testInjectMembers_WithProtectedInjectedProperty_AssignsProperty(): void
    {
        // Arrange
        $obj = new FakeClassNoConstructor();
        $injector = $this->createInjector([FakeClassNoConstructor::class => fn() => $obj]);

        // Act
        $instance = $injector->injectMembers($injector->instantiate(FakeClassWithInjectedProperties::class));

        // Assert
        self::assertSame($obj, $instance->getProtectedProperty());
    }

    public function testInjectMembers_WithPrivateInjectedProperty_AssignsProperty(): void
    {
        // Arrange
        $obj = new FakeClassNoConstructor();
        $injector = $this->createInjector([FakeClassNoConstructor::class => fn() => $obj]);

        // Act
        $instance = $injector->injectMembers($injector->instantiate(FakeClassWithInjectedProperties::class));

        // Assert
        self::assertSame($obj, $instance->getPrivateProperty());
    }

    public function testInjectMembers_WithKeyedInjectedProperty_AssignsKeyedService(): void
    {
        // Arrange
        $keyed = new FakeClassNoConstructor();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder
                ->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor())
                ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $keyed),
        );
        $injector = Injector::createDefault($container);

        // Act
        $instance = $injector->injectMembers($injector->instantiate(FakeClassWithInjectedProperties::class));

        // Assert
        self::assertSame($keyed, $instance->keyedProperty);
    }

    public function testInjectMembers_WithUnresolvableNullableInjectedProperty_AssignsNull(): void
    {
        // Arrange: FakeInterfaceOne is not registered, so the nullable property falls back to null.
        $injector = $this->createInjector([
            FakeClassNoConstructor::class => fn() => new FakeClassNoConstructor(),
        ]);

        // Act
        $instance = $injector->injectMembers($injector->instantiate(FakeClassWithInjectedProperties::class));

        // Assert
        self::assertNull($instance->optionalProperty);
    }

    public function testInjectMembers_WithProtectedInjectMethod_CallsMethod(): void
    {
        // Arrange
        $obj = new FakeClassNoConstructor();
        $injector = $this->createInjector([FakeClassNoConstructor::class => fn() => $obj]);

        // Act
        $instance = $injector->injectMembers($injector->instantiate(FakeClassWithNonPublicInjectMethods::class));

        // Assert
        self::assertSame($obj, $instance->protectedSetterValue);
    }

    public function testInjectMembers_WithPrivateInjectMethod_CallsMethod(): void
    {
        // Arrange
        $obj = new FakeClassNoConstructor();
        $injector = $this->createInjector([FakeClassNoConstructor::class => fn() => $obj]);

        // Act
        $instance = $injector->injectMembers($injector->instantiate(FakeClassWithNonPublicInjectMethods::class));

        // Assert
        self::assertSame($obj, $instance->privateSetterValue);
    }

    public function testInjectMembers_WithCustomPostInstantiationHook_AppliesHookToInstance(): void
    {
        // Arrange: a spy hook records the instances it is applied to.
        $spy = new class implements PostInstantiationHookInterface {
            /** @var list<object> */
            public array $injected = [];

            public function postInstantiate(object $instance): void
            {
                $this->injected[] = $instance;
            }
        };
        $resolver = new ContainerParameterResolver(new FakeContainer());
        $injector = new Injector($resolver, new ReflectionInstantiationStrategy($resolver), $spy);

        // Act
        $instance = $injector->injectMembers($injector->instantiate(FakeClassNoConstructor::class));

        // Assert
        self::assertSame([$instance], $spy->injected);
    }

    public function testInstantiate_WithCustomPostInstantiationHook_DoesNotApplyHook(): void
    {
        // Arrange: instantiate only constructs; member injection is the separate injectMembers() step.
        $spy = new class implements PostInstantiationHookInterface {
            /** @var list<object> */
            public array $injected = [];

            public function postInstantiate(object $instance): void
            {
                $this->injected[] = $instance;
            }
        };
        $resolver = new ContainerParameterResolver(new FakeContainer());
        $injector = new Injector($resolver, new ReflectionInstantiationStrategy($resolver), $spy);

        // Act
        $injector->instantiate(FakeClassNoConstructor::class);

        // Assert
        self::assertSame([], $spy->injected);
    }

    public function testInjectMembers_WithStaticInjectMethod_ThrowsInjectorException(): void
    {
        // Arrange: the fake has #[Inject] on a static method, which is always a misconfiguration.
        $injector = $this->createInjector([
            FakeClassNoConstructor::class => fn() => new FakeClassNoConstructor(),
        ]);

        // Act & Assert
        $this->expectException(InjectorException::class);
        $injector->injectMembers($injector->instantiate(FakeClassWithStaticInjectMethod::class));
    }

    public function testInjectMembers_WithKeyOnPropertyWithoutInject_ThrowsInjectorException(): void
    {
        // Arrange: the fake has a #[Key] property that is missing #[Inject], which is always a misconfiguration.
        $injector = $this->createInjector();

        // Act & Assert
        $this->expectException(InjectorException::class);
        $injector->injectMembers($injector->instantiate(FakeClassWithDanglingKeyProperty::class));
    }

    public function testInjectMembers_WithUnresolvableInjectedProperty_ThrowsPropertyResolutionException(): void
    {
        // Arrange: nothing is registered, so the first required injected property cannot be resolved.
        $injector = $this->createInjector();

        // Act & Assert
        $this->expectException(PropertyResolutionException::class);
        $injector->injectMembers($injector->instantiate(FakeClassWithInjectedProperties::class));
    }

    public function testInjectMembers_WithUnresolvableInjectPropertyType_ThrowsInjectorException(): void
    {
        // Arrange: a scalar #[Inject] property can never resolve to a service, so instantiation must fail rather
        // than silently assign a value.
        $injector = $this->createInjector();

        // Act & Assert
        $this->expectException(InjectorException::class);
        $injector->injectMembers($injector->instantiate(FakeClassWithScalarInjectProperty::class));
    }

    public function testInstantiate_WithPlainParameterResolver_UsesReflection(): void
    {
        // Arrange: a plain resolver (not a TypeParameterResolverInterface) still works via reflection.
        $dependency = new FakeClassNoConstructor();
        $resolver = new class ($dependency) implements ParameterResolverInterface {
            public function __construct(private readonly FakeClassNoConstructor $dependency) {}

            public function resolveParameter(ReflectionParameter $rParam): mixed
            {
                return $this->dependency;
            }

            public function resolveProperty(ReflectionProperty $rProperty, string|UnitEnum|null $key): mixed
            {
                return $this->dependency;
            }
        };
        $injector = new Injector(
            $resolver,
            new ReflectionInstantiationStrategy($resolver),
            new InjectAttributeMemberInjector($resolver),
        );

        // Act
        $instance = $injector->instantiate(FakeClassWithConstructor::class);

        // Assert
        self::assertSame($dependency, $instance->obj);
    }

    public function testInstantiate_WithCustomProducingStrategy_UsesItsInstance(): void
    {
        // Arrange: a lone custom strategy that produces the instance itself.
        $strategy = new class implements InstantiationStrategyInterface {
            public int $calls = 0;

            public function tryInstantiate(string $className, array $params): object
            {
                $this->calls++;

                return new $className();
            }
        };
        $resolver = new ContainerParameterResolver(new FakeContainer());
        $injector = new Injector($resolver, $strategy, new InjectAttributeMemberInjector($resolver));

        // Act
        $instance = $injector->instantiate(FakeClassNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
        self::assertSame(1, $strategy->calls);
    }

    public function testInstantiate_WithNoApplicableStrategy_ThrowsInjectorException(): void
    {
        // Arrange: a lone strategy that declines everything.
        $declining = new class implements InstantiationStrategyInterface {
            public function tryInstantiate(string $className, array $params): ?object
            {
                return null;
            }
        };
        $resolver = new ContainerParameterResolver(new FakeContainer());
        $injector = new Injector($resolver, $declining, new InjectAttributeMemberInjector($resolver));

        // Act & Assert
        $this->expectException(InjectorException::class);
        $injector->instantiate(FakeClassNoConstructor::class);
    }

    public function testInstantiate_WithUnionDependency_ResolvesFirstAvailableAlternative(): void
    {
        // Arrange: only the second alternative of the union is registered.
        $instance = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([
            FakeInterfaceTwo::class => fn() => $instance,
        ]);

        // Act
        $result = $injector->instantiate(FakeClassWithUnionDependency::class);

        // Assert
        self::assertSame($instance, $result->obj);
    }

    public function testInstantiate_WithIntersectionDependency_ResolvesServiceSatisfyingAllTypes(): void
    {
        // Arrange: a service implementing both interfaces, registered under the first.
        $instance = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([
            FakeInterfaceOne::class => fn() => $instance,
        ]);

        // Act
        $result = $injector->instantiate(FakeClassWithIntersectionDependency::class);

        // Assert
        self::assertSame($instance, $result->obj);
    }

    #[RequiresPhp('>= 8.2.0')]
    public function testInstantiate_WithDnfDependency_ResolvesViaIntersectionAlternative(): void
    {
        // Arrange: for (FakeInterfaceOne&FakeInterfaceTwo)|FakeInterfaceThree, register a service satisfying the
        // intersection alternative under its first member.
        $instance = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([
            FakeInterfaceOne::class => fn() => $instance,
        ]);

        // Act
        $result = $injector->instantiate(FakeClassWithDnfDependency::class);

        // Assert
        self::assertSame($instance, $result->obj);
    }

    public function testInstantiate_WithOverrideForUnregisteredDependency_UsesOverride(): void
    {
        // Arrange: throwable is resolvable from the container; runtimeException is not registered, only supplied here.
        $throwable = new LogicException();
        $override = new RuntimeException();
        $injector = $this->createInjector([
            Throwable::class => fn() => $throwable,
        ]);

        // Act
        $instance = $injector->instantiate(FakeClassWithDependencies::class, [
            'runtimeException' => $override,
        ]);

        // Assert
        self::assertSame($throwable, $instance->throwable);
        self::assertSame($override, $instance->runtimeException);
    }

    public function testInstantiate_WithKeyedDependency_ResolvesKeyedServiceOverUnkeyed(): void
    {
        // Arrange
        $unkeyed = new FakeClassNoConstructor();
        $keyed = new FakeClassNoConstructor();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder
                ->addSingletonInstance(FakeClassNoConstructor::class, $unkeyed)
                ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $keyed),
        );
        $injector = Injector::createDefault($container);

        // Act
        $instance = $injector->instantiate(FakeClassWithKeyedDependency::class);

        // Assert
        self::assertSame($keyed, $instance->dependency);
        self::assertNotSame($unkeyed, $instance->dependency);
    }

    public function testCall_WithFunction_InjectsDependenciesAndReturnsResult(): void
    {
        // Arrange
        $logicException = new LogicException('Message 1');
        $runtimeException = new RuntimeException('Message 2');
        $injector = $this->createInjector([
            Throwable::class => fn() => $logicException,
            LogicException::class => fn() => $logicException,
            RuntimeException::class => fn() => $runtimeException,
        ]);

        // Act
        $result = $injector->call(fn(
            Throwable $e1,
            RuntimeException $e2,
            string $a,
            $b,
        ) => [$e1, $e2, $a, $b], [
            'a' => 'a',
            'b' => 2,
        ]);

        // Assert
        self::assertSame([$logicException, $runtimeException, 'a', 2], $result);
    }

    public function testCall_WithUnresolvableNullableDependency_InjectsNull(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act
        $result = $injector->call(fn(?FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertNull($result);
    }

    public function testCall_WithUntypedDependency_InjectsNull(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act
        $result = $injector->call(fn($var) => $var);

        // Assert
        self::assertNull($result);
    }

    public function testCall_WithUnresolvableDependency_ThrowsInjectorException(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act & Assert
        $this->expectException(InjectorException::class);
        $injector->call(fn(FakeClassNoConstructor $obj) => $obj);
    }

    public function testCall_WithDependencyWithUnresolvableDependency_ThrowsInjectorException(): void
    {
        // Arrange: FakeClassWithConstructor is deliberately left unregistered, since registering an autowired class
        // whose own dependency is unresolvable would now fail ContainerBuilder::build() itself (see
        // ContainerBuilderTest::testBuild_WithDefectiveConfiguration_ThrowsAggregatedValidationException) rather than
        // surfacing as an InjectorException when the injector later resolves it.
        $container = self::createBuilder()->build();
        $injector = Injector::createDefault($container);

        // Act & Assert
        $this->expectException(InjectorException::class);
        $injector->call(fn(FakeClassWithConstructor $obj) => $obj);
    }

    public function testCall_WithBuiltinType_ThrowsParameterResolutionException(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act
        $fn = static fn() => $injector->call(fn(string $a) => $a);

        // Assert
        self::assertThrowsParameterResolutionException(
            __NAMESPACE__ . '\\{closure}',
            'a',
            null,
            $fn,
        );
    }

    public function testCall_WithUnionType_ResolvesFromLeftToRight(): void
    {
        // Arrange
        $instance1 = new FakeClassImplementsInterfaces();
        $instance2 = new FakeClassImplementsInterfaces();

        $injector = $this->createInjector([
            FakeInterfaceOne::class => fn() => $instance1,
            FakeInterfaceTwo::class => fn() => $instance2,
        ]);

        // Act
        $result1 = $injector->call(fn(FakeInterfaceOne|FakeInterfaceTwo $obj) => $obj);
        $result2 = $injector->call(fn(FakeInterfaceTwo|FakeInterfaceOne $obj) => $obj);

        // Assert
        self::assertSame($instance1, $result1);
        self::assertSame($instance2, $result2);
    }

    public function testCall_WithUnionTypeIncludingBuiltinType_ResolvesNamedTypeListedAfterBuiltinType(): void
    {
        // Arrange
        $instance = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([
            FakeInterfaceOne::class => fn() => $instance,
        ]);

        // Act
        $result = $injector->call(fn(string|FakeInterfaceOne $obj) => $obj);

        // Assert
        self::assertSame($instance, $result);
    }

    public function testCall_WithNoResolvableTypeInUnionType_ThrowsParameterResolutionException(): void
    {
        // Arrange
        $injector = $this->createInjector([
            FakeClassImplementsInterfaces::class => fn() => new FakeClassImplementsInterfaces(),
        ]);

        // Act
        $fn = static fn() => $injector->call(fn(FakeInterfaceOne|FakeInterfaceTwo $obj) => $obj);

        // Assert
        self::assertThrowsParameterResolutionException(
            __NAMESPACE__ . '\\{closure}',
            'obj',
            null,
            $fn,
        );
    }

    public function testCall_WithIntersectionTypeOnlyFirstInContainer_ReturnsInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([FakeInterfaceOne::class => fn() => $expectedInstance]);

        // Act
        $result = $injector->call(fn(FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_WithIntersectionTypeOnlySecondInContainer_ReturnsInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([FakeInterfaceTwo::class => fn() => $expectedInstance]);

        // Act
        $result = $injector->call(fn(FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_WithIntersectionBothInContainer_ResolvesFromLeftToRight(): void
    {
        // Arrange
        $instance1 = new FakeClassImplementsInterfaces();
        $instance2 = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([
            FakeInterfaceOne::class => fn() => $instance1,
            FakeInterfaceTwo::class => fn() => $instance2,
        ]);

        // Act
        $result1 = $injector->call(fn(FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj);
        $result2 = $injector->call(fn(FakeInterfaceTwo&FakeInterfaceOne $obj) => $obj);

        // Assert
        self::assertSame($instance1, $result1);
        self::assertSame($instance2, $result2);
    }

    public function testCall_WithIntersectionTypeNotImplementingOneType_ThrowsParameterResolutionException(): void
    {
        // Arrange
        $injector = $this->createInjector([FakeInterfaceOne::class => fn() => new FakeClassImplementsInterfaces()]);

        // Act
        $fn = static fn() => $injector->call(fn(FakeInterfaceOne&FakeInterfaceThree $obj) => $obj);

        // Assert
        self::assertThrowsParameterResolutionException(
            __NAMESPACE__ . '\\{closure}',
            'obj',
            null,
            $fn,
        );
    }

    public function testCall_WithParameterHavingCircularDependency_ThrowsParameterResolutionException(): void
    {
        // Arrange: a self-referential factory *parameter* is a cycle build-time validation proves and rejects, so
        // the cycle hides in the factory body instead: invisible to the validator, caught by the runtime guard.
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingletonFactory(
                FakeClassNoConstructor::class,
                static fn(ContainerInterface $c): FakeClassNoConstructor
                    => $c->get(FakeClassNoConstructor::class),
            ),
        );

        $injector = Injector::createDefault($container);

        // Act
        $fn = static fn() => $injector->call(fn(FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertThrowsParameterResolutionException(
            __NAMESPACE__ . '\\{closure}',
            'obj',
            /** @param ClassResolutionException<FakeClassNoConstructor> $exception */
            static fn(ClassResolutionException $exception) => self::assertClassResolutionException(
                FakeClassNoConstructor::class,
                /** @param CircularDependencyException<FakeClassNoConstructor> $exception */
                static fn(CircularDependencyException $exception) => self::assertCircularDependencyException(
                    FakeClassNoConstructor::class,
                    $exception,
                ),
                $exception,
            ),
            $fn,
        );
    }
}
