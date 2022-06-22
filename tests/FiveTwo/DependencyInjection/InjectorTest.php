<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

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
     * @psalm-param array<class-string, callable():object> $classMapping
     * @phpstan-param array<class-string, callable():object> $classMapping
     * @return Injector
     */
    protected function createInjector(array $classMapping = []): Injector
    {
        return new Injector(new FakeContainer($classMapping));
    }

    public function testInstantiate_WithDependenciesInContainer_ReturnsInstanceWithValuesFromContainer(): void
    {
        $logicException = new LogicException();
        $instance = $this->createInjector([
            Throwable::class => fn () => $logicException,
            LogicException::class => fn () => $logicException,
            RuntimeException::class => fn () => new RuntimeException('test')
        ])->instantiate(FakeClassUsingContexts::class);

        self::assertInstanceOf(FakeClassUsingContexts::class, $instance);
        self::assertSame($logicException, $instance->throwable);
        self::assertSame('test', $instance->runtimeException->getMessage());
    }

    public function testInstantiate_WithDependenciesWithDefaultValues_ReturnsInstanceUsingDefaultValues(): void
    {
        $injector = $this->createInjector();

        self::assertInstanceOf(Exception::class, $injector->instantiate(Exception::class));
    }

    public function testInstantiate_WithInvalidClass_ThrowsDependencyInjectionException(): void
    {
        $injector = $this->createInjector();

        $this->expectException(DependencyInjectionException::class);
        /**
         * @psalm-suppress ArgumentTypeCoercion,UndefinedClass warns about issue currently under test
         * @phpstan-ignore-next-line warns about issue currently under test
         */
        $injector->instantiate('NonExistentClass');
    }

    public function testInstantiate_WithNonInstantiableClass_ThrowsDependencyInjectionException(): void
    {
        $injector = $this->createInjector();

        $this->expectException(DependencyInjectionException::class);
        $injector->instantiate(FakeAbstractClass::class);
    }

    public function testInstantiate_WithMissingDependency_ThrowsDependencyInjectionException(): void
    {
        $injector = $this->createInjector();

        // missing argument of type RuntimeException
        $this->expectException(DependencyInjectionException::class);
        $injector->instantiate(FakeClassUsingContexts::class);
    }

    public function testInstantiate_WithNamedParametersAlsoResolvableFromContainer_UsesNamedParameters(): void
    {
        $injector = $this->createInjector([
            Throwable::class => fn () => new LogicException(),
            RuntimeException::class => fn () => new RuntimeException()
        ]);

        self::assertSame(
            $override = new RuntimeException(),
            $injector->instantiate(FakeClassUsingContexts::class, [
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
            $injector->instantiate(FakeClassUsingContexts::class, [
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

    public function testCall_WithUnresolvableDependency_ThrowsDependencyInjectionException(): void
    {
        $injector = $this->createInjector();

        $this->expectException(DependencyInjectionException::class);
        $injector->call(fn (FakeClassNoConstructor $obj) => $obj);
    }

    public function testCall_WithUntypedDependency_ThrowsUnresolvedParameterException(): void
    {
        $injector = $this->createInjector();

        self::assertUnresolvedParameterException(
            'Closure::__invoke',
            'a',
            null,
            fn () => $injector->call(fn ($a) => $a)
        );
    }

    public function testCall_WithBuiltinType_ThrowsUnresolvedParameterException(): void
    {
        $injector = $this->createInjector();

        self::assertUnresolvedParameterException(
            'Closure::__invoke',
            'a',
            'string',
            fn () => $injector->call(fn (string $a) => $a)
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

    public function testCall_WithNoResolvableTypeInUnionType_ThrowsUnresolvedParameterException(): void
    {
        $injector = $this->createInjector([
            FakeClassImplementsInterfaces::class => fn () => new FakeClassImplementsInterfaces()
        ]);

        self::assertUnresolvedParameterException(
            'Closure::__invoke',
            'obj',
            FakeInterfaceOne::class . '|' . FakeInterfaceTwo::class,
            fn () => $injector->call(fn (FakeInterfaceOne|FakeInterfaceTwo $obj) => $obj)
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

    public function testCall_WithIntersectionTypeNotImplementingOneType_ThrowsUnresolvedParameterException(): void
    {
        $injector = $this->createInjector([FakeInterfaceOne::class => fn () => new FakeClassImplementsInterfaces()]);

        self::assertUnresolvedParameterException(
            'Closure::__invoke',
            'obj',
            FakeInterfaceOne::class . '&' . FakeInterfaceThree::class,
            fn () => $injector->call(fn (FakeInterfaceOne&FakeInterfaceThree $obj) => $obj)
        );
    }

    public function testCall_WithParameterHavingCircularDependency_ThrowsCircularParameterException(): void
    {
        $container = new Container();
        $container->addSingletonFactory(
            FakeClassNoConstructor::class,
            fn (FakeClassNoConstructor $obj) => $obj
        );

        $injector = new Injector($container);

        self::assertCircularParameterException(
            'Closure::__invoke',
            'obj',
            FakeClassNoConstructor::class,
            fn () => $injector->call(fn (FakeClassNoConstructor $obj) => $obj)
        );
    }
}
