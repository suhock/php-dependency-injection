<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Exception;
use LogicException;
use RuntimeException;
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
     * @return Injector
     */
    protected function createInjector(array $classMapping = []): Injector
    {
        return new ContainerInjector(new FakeContainer($classMapping));
    }

    public function testInstantiate_WithDependenciesInContainer_ReturnsInstanceWithValuesFromContainer(): void
    {
        $logicException = new LogicException();
        $instance = $this->createInjector([
            Throwable::class => fn () => $logicException,
            LogicException::class => fn () => $logicException,
            RuntimeException::class => fn () => new RuntimeException('test')
        ])->instantiate(FakeClassWithContexts::class);

        self::assertInstanceOf(FakeClassWithContexts::class, $instance);
        self::assertSame($logicException, $instance->throwable);
        self::assertSame('test', $instance->runtimeException->getMessage());
    }

    public function testInstantiate_WithDependenciesWithDefaultValues_ReturnsInstanceUsingDefaultValues(): void
    {
        $injector = $this->createInjector();

        self::assertInstanceOf(Exception::class, $injector->instantiate(Exception::class));
    }

    public function testInstantiate_WithInvalidClass_ThrowsInjectorException(): void
    {
        $injector = $this->createInjector();

        $this->expectException(InjectorException::class);
        /**
         * @phpstan-ignore-next-line warns about issue currently under test
         */
        $injector->instantiate('NonExistentClass');
    }

    public function testInstantiate_WithNonInstantiableClass_ThrowsInjectorException(): void
    {
        $injector = $this->createInjector();

        $this->expectException(InjectorException::class);
        $injector->instantiate(FakeAbstractClass::class);
    }

    public function testInstantiate_WithMissingDependency_ThrowsInjectorException(): void
    {
        $injector = $this->createInjector();

        // missing argument of type RuntimeException
        $this->expectException(InjectorException::class);
        $injector->instantiate(FakeClassWithContexts::class);
    }

    public function testInstantiate_WithNamedParametersAlsoResolvableFromContainer_UsesNamedParameters(): void
    {
        $injector = $this->createInjector([
            Throwable::class => fn () => new LogicException(),
            RuntimeException::class => fn () => new RuntimeException()
        ]);

        self::assertSame(
            $override = new RuntimeException(),
            $injector->instantiate(FakeClassWithContexts::class, [
                'runtimeException' => $override
            ])->runtimeException
        );
    }

    public function testInstantiate_WithPositionalParametersAlsoResolvableFromContainer_UsesPositionalParameters(): void
    {
        $injector = $this->createInjector([
            Throwable::class => fn () => new LogicException(),
            RuntimeException::class => fn () => new RuntimeException()
        ]);

        self::assertSame(
            $override = new RuntimeException(),
            $injector->instantiate(FakeClassWithContexts::class, [
                1 => $override
            ])->runtimeException
        );
    }

    public function testInstantiate_WithNoConstructor_ReturnsInstance(): void
    {
        $injector = $this->createInjector();
        $instance = $injector->instantiate(FakeClassNoConstructor::class);

        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
    }

    public function testInstantiate_WithAutowireFunction_CallsAutowireFunction(): void
    {
        $obj = new FakeClassNoConstructor();
        $injector = $this->createInjector([
            FakeClassNoConstructor::class => fn () => $obj
        ]);
        $instance = $injector->instantiate(FakeClassWithAutowireFunction::class);

        self::assertSame($obj, $instance->obj);
    }

    public function testCall_WithFunction_InjectsDependenciesAndReturnsResult(): void
    {
        $logicException = new LogicException('Message 1');
        $runtimeException = new RuntimeException('Message 2');
        $injector = $this->createInjector([
            Throwable::class => fn () => $logicException,
            LogicException::class => fn () => $logicException,
            RuntimeException::class => fn () => $runtimeException
        ]);

        self::assertSame(
            [$logicException, $runtimeException, 'a', 2],
            $injector->call(fn (
                Throwable $e1,
                RuntimeException $e2,
                string $a,
                $b
            ) => [$e1, $e2, $a, $b], [
                'a' => 'a',
                'b' => 2
            ])
        );
    }

    public function testCall_WithUnresolvableNullableDependency_InjectsNull(): void
    {
        $injector = $this->createInjector();

        self::assertNull($injector->call(fn (?FakeClassNoConstructor $obj) => $obj));
    }

    public function testCall_WithUntypedDependency_InjectsNull(): void
    {
        $injector = $this->createInjector();

        self::assertNull($injector->call(fn ($var) => $var));
    }

    public function testCall_WithUnresolvableDependency_ThrowsInjectorException(): void
    {
        $injector = $this->createInjector();

        $this->expectException(InjectorException::class);
        $injector->call(fn (FakeClassNoConstructor $obj) => $obj);
    }

    public function testCall_WithDependencyWithUnresolvableDependency_ThrowsInjectorException(): void
    {
        $container = new Container();
        $container->addSingletonClass(FakeClassWithConstructor::class);
        $injector = new ContainerInjector($container);

        $this->expectException(InjectorException::class);
        $injector->call(fn (FakeClassWithConstructor $obj) => $obj);
    }

    public function testCall_WithBuiltinType_ThrowsParameterResolutionException(): void
    {
        $injector = $this->createInjector();

        self::assertThrowsParameterResolutionException(
            __NAMESPACE__ . '\\{closure}',
            'a',
            null,
            static fn () => $injector->call(fn (string $a) => $a)
        );
    }

    public function testCall_WithUnionType_ResolvesFromLeftToRight(): void
    {
        $instance1 = new FakeClassImplementsInterfaces();
        $instance2 = new FakeClassImplementsInterfaces();

        $injector = $this->createInjector([
            FakeInterfaceOne::class => fn () => $instance1,
            FakeInterfaceTwo::class => fn () => $instance2
        ]);

        self::assertSame(
            $instance1,
            $injector->call(fn (FakeInterfaceOne|FakeInterfaceTwo $obj) => $obj)
        );

        self::assertSame(
            $instance2,
            $injector->call(fn (FakeInterfaceTwo|FakeInterfaceOne $obj) => $obj)
        );
    }

    public function testCall_WithUnionTypeIncludingBuiltinType_ResolvesNamedTypeListedAfterBuiltinType(): void
    {
        $instance = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([
            FakeInterfaceOne::class => fn () => $instance
        ]);

        self::assertSame(
            $instance,
            $injector->call(fn (string|FakeInterfaceOne $obj) => $obj)
        );
    }

    public function testCall_WithNoResolvableTypeInUnionType_ThrowsParameterResolutionException(): void
    {
        $injector = $this->createInjector([
            FakeClassImplementsInterfaces::class => fn () => new FakeClassImplementsInterfaces()
        ]);

        self::assertThrowsParameterResolutionException(
            __NAMESPACE__ . '\\{closure}',
            'obj',
            null,
            static fn () => $injector->call(fn (FakeInterfaceOne|FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_WithIntersectionTypeOnlyFirstInContainer_ReturnsInstance(): void
    {
        $expectedInstance = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([FakeInterfaceOne::class => fn () => $expectedInstance]);

        self::assertSame(
            $expectedInstance,
            $injector->call(fn (FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_WithIntersectionTypeOnlySecondInContainer_ReturnsInstance(): void
    {
        $expectedInstance = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([FakeInterfaceTwo::class => fn () => $expectedInstance]);

        self::assertSame(
            $expectedInstance,
            $injector->call(fn (FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_WithIntersectionBothInContainer_ResolvesFromLeftToRight(): void
    {
        $instance1 = new FakeClassImplementsInterfaces();
        $instance2 = new FakeClassImplementsInterfaces();
        $injector = $this->createInjector([
            FakeInterfaceOne::class => fn () => $instance1,
            FakeInterfaceTwo::class => fn () => $instance2
        ]);

        self::assertSame(
            $instance1,
            $injector->call(fn (FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj)
        );
        self::assertSame(
            $instance2,
            $injector->call(fn (FakeInterfaceTwo&FakeInterfaceOne $obj) => $obj)
        );
    }

    public function testCall_WithIntersectionTypeNotImplementingOneType_ThrowsParameterResolutionException(): void
    {
        $injector = $this->createInjector([FakeInterfaceOne::class => fn () => new FakeClassImplementsInterfaces()]);

        self::assertThrowsParameterResolutionException(
            __NAMESPACE__ . '\\{closure}',
            'obj',
            null,
            static fn () => $injector->call(fn (FakeInterfaceOne&FakeInterfaceThree $obj) => $obj)
        );
    }

    public function testCall_WithParameterHavingCircularDependency_ThrowsParameterResolutionException(): void
    {
        $container = new Container();
        $container->addSingletonFactory(
            FakeClassNoConstructor::class,
            fn (FakeClassNoConstructor $obj) => $obj
        );

        $injector = new ContainerInjector($container);

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
            static fn () => $injector->call(fn (FakeClassNoConstructor $obj) => $obj)
        );
    }
}
