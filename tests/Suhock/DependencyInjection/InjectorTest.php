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
use RuntimeException;
use Suhock\DependencyInjection\Fakes\FakeAbstractClass;
use Suhock\DependencyInjection\Fakes\FakeClassImplementsInterfaces;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithAutowireFunction;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithDependencies;
use Suhock\DependencyInjection\Fakes\FakeContainer;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\Fakes\FakeInterfaceThree;
use Suhock\DependencyInjection\Fakes\FakeInterfaceTwo;
use Throwable;

/**
 * Test suite for {@see Injector}.
 */
class InjectorTest extends DependencyInjectionTestCase
{
    /**
     * @param array<callable> $classMapping
     *
     * @phpstan-param array<class-string, callable():object> $classMapping
     */
    protected function createInjector(array $classMapping = []): Injector
    {
        return new ContainerInjector(new FakeContainer($classMapping));
    }

    public function testInstantiate_WithDependenciesInContainer_ReturnsInstanceWithValuesFromContainer(): void
    {
        // Arrange
        $logicException = new LogicException();

        // Act
        $instance = $this->createInjector([
            Throwable::class => fn () => $logicException,
            LogicException::class => fn () => $logicException,
            RuntimeException::class => fn () => new RuntimeException('test')
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
            Throwable::class => fn () => new LogicException(),
            RuntimeException::class => fn () => new RuntimeException()
        ]);
        $override = new RuntimeException();

        // Act
        $result = $injector->instantiate(FakeClassWithDependencies::class, [
            'runtimeException' => $override
        ])->runtimeException;

        // Assert
        self::assertSame($override, $result);
    }

    public function testInstantiate_WithPositionalParametersAlsoResolvableFromContainer_UsesPositionalParameters(): void
    {
        // Arrange
        $injector = $this->createInjector([
            Throwable::class => fn () => new LogicException(),
            RuntimeException::class => fn () => new RuntimeException()
        ]);
        $override = new RuntimeException();

        // Act
        $result = $injector->instantiate(FakeClassWithDependencies::class, [
            1 => $override
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

    public function testInstantiate_WithAutowireFunction_CallsAutowireFunction(): void
    {
        // Arrange
        $obj = new FakeClassNoConstructor();
        $injector = $this->createInjector([
            FakeClassNoConstructor::class => fn () => $obj
        ]);

        // Act
        $instance = $injector->instantiate(FakeClassWithAutowireFunction::class);

        // Assert
        self::assertSame($obj, $instance->obj);
    }

    public function testCall_WithFunction_InjectsDependenciesAndReturnsResult(): void
    {
        // Arrange
        $logicException = new LogicException('Message 1');
        $runtimeException = new RuntimeException('Message 2');
        $injector = $this->createInjector([
            Throwable::class => fn () => $logicException,
            LogicException::class => fn () => $logicException,
            RuntimeException::class => fn () => $runtimeException
        ]);

        // Act
        $result = $injector->call(fn (
            Throwable $e1,
            RuntimeException $e2,
            string $a,
            $b
        ) => [$e1, $e2, $a, $b], [
            'a' => 'a',
            'b' => 2
        ]);

        // Assert
        self::assertSame([$logicException, $runtimeException, 'a', 2], $result);
    }

    public function testCall_WithUnresolvableNullableDependency_InjectsNull(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act
        $result = $injector->call(fn (?FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertNull($result);
    }

    public function testCall_WithUntypedDependency_InjectsNull(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act
        $result = $injector->call(fn ($var) => $var);

        // Assert
        self::assertNull($result);
    }

    public function testCall_WithUnresolvableDependency_ThrowsInjectorException(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act & Assert
        $this->expectException(InjectorException::class);
        $injector->call(fn (FakeClassNoConstructor $obj) => $obj);
    }

    public function testCall_WithDependencyWithUnresolvableDependency_ThrowsInjectorException(): void
    {
        // Arrange
        $container = new Container();
        $container->addSingletonClass(FakeClassWithConstructor::class);
        $injector = new ContainerInjector($container);

        // Act & Assert
        $this->expectException(InjectorException::class);
        $injector->call(fn (FakeClassWithConstructor $obj) => $obj);
    }

    public function testCall_WithBuiltinType_ThrowsParameterResolutionException(): void
    {
        // Arrange
        $injector = $this->createInjector();

        // Act
        $fn = static fn () => $injector->call(fn (string $a) => $a);

        // Assert
        self::assertThrowsParameterResolutionException(
            __NAMESPACE__ . '\\{closure}',
            'a',
            null,
            $fn
        );
    }

    public function testCall_WithUnionType_ResolvesFromLeftToRight(): void
    {
        // Arrange
        $instance1 = new FakeClassImplementsInterfaces();
        $instance2 = new FakeClassImplementsInterfaces();

        $injector = $this->createInjector([
            FakeInterfaceOne::class => fn () => $instance1,
            FakeInterfaceTwo::class => fn () => $instance2
        ]);

        // Act
        $result1 = $injector->call(fn (FakeInterfaceOne|FakeInterfaceTwo $obj) => $obj);
        $result2 = $injector->call(fn (FakeInterfaceTwo|FakeInterfaceOne $obj) => $obj);

        // Assert
        self::assertSame($instance1, $result1);
        self::assertSame($instance2, $result2);
    }

    public function testCall_WithUnionTypeIncludingBuiltinType_ResolvesNamedTypeListedAfterBuiltinType(): void
    {
        // Arrange
        $instance = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([
            FakeInterfaceOne::class => fn () => $instance
        ]);

        // Act
        $result = $injector->call(fn (string|FakeInterfaceOne $obj) => $obj);

        // Assert
        self::assertSame($instance, $result);
    }

    public function testCall_WithNoResolvableTypeInUnionType_ThrowsParameterResolutionException(): void
    {
        // Arrange
        $injector = $this->createInjector([
            FakeClassImplementsInterfaces::class => fn () => new FakeClassImplementsInterfaces()
        ]);

        // Act
        $fn = static fn () => $injector->call(fn (FakeInterfaceOne|FakeInterfaceTwo $obj) => $obj);

        // Assert
        self::assertThrowsParameterResolutionException(
            __NAMESPACE__ . '\\{closure}',
            'obj',
            null,
            $fn
        );
    }

    public function testCall_WithIntersectionTypeOnlyFirstInContainer_ReturnsInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([FakeInterfaceOne::class => fn () => $expectedInstance]);

        // Act
        $result = $injector->call(fn (FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_WithIntersectionTypeOnlySecondInContainer_ReturnsInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([FakeInterfaceTwo::class => fn () => $expectedInstance]);

        // Act
        $result = $injector->call(fn (FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj);

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testCall_WithIntersectionBothInContainer_ResolvesFromLeftToRight(): void
    {
        // Arrange
        $instance1 = new FakeClassImplementsInterfaces();
        $instance2 = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([
            FakeInterfaceOne::class => fn () => $instance1,
            FakeInterfaceTwo::class => fn () => $instance2
        ]);

        // Act
        $result1 = $injector->call(fn (FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj);
        $result2 = $injector->call(fn (FakeInterfaceTwo&FakeInterfaceOne $obj) => $obj);

        // Assert
        self::assertSame($instance1, $result1);
        self::assertSame($instance2, $result2);
    }

    public function testCall_WithIntersectionTypeNotImplementingOneType_ThrowsParameterResolutionException(): void
    {
        // Arrange
        $injector = $this->createInjector([FakeInterfaceOne::class => fn () => new FakeClassImplementsInterfaces()]);

        // Act
        $fn = static fn () => $injector->call(fn (FakeInterfaceOne&FakeInterfaceThree $obj) => $obj);

        // Assert
        self::assertThrowsParameterResolutionException(
            __NAMESPACE__ . '\\{closure}',
            'obj',
            null,
            $fn
        );
    }

    public function testCall_WithParameterHavingCircularDependency_ThrowsParameterResolutionException(): void
    {
        // Arrange
        $container = new Container();
        $container->addSingletonFactory(
            FakeClassNoConstructor::class,
            fn (FakeClassNoConstructor $obj) => $obj
        );

        $injector = new ContainerInjector($container);

        // Act
        $fn = static fn () => $injector->call(fn (FakeClassNoConstructor $obj) => $obj);

        // Assert
        self::assertThrowsParameterResolutionException(
            __NAMESPACE__ . '\\{closure}',
            'obj',
            /** @param ClassResolutionException<FakeClassNoConstructor> $exception */
            static fn (ClassResolutionException $exception) => self::assertClassResolutionException(
                FakeClassNoConstructor::class,
                /** @param CircularDependencyException<FakeClassNoConstructor> $exception */
                static fn (CircularDependencyException $exception) => self::assertCircularDependencyException(
                    FakeClassNoConstructor::class,
                    $exception
                ),
                $exception
            ),
            $fn
        );
    }
}
