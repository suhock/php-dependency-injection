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
    protected function create(array $classMapping = []): Injector
    {
        return new Injector(new FakeContainer($classMapping));
    }

    public function testInstantiate(): void
    {
        $logicException = new LogicException();
        $instance = $this->create([
            Throwable::class => fn () => $logicException,
            LogicException::class => fn () => $logicException,
            RuntimeException::class => fn () => new RuntimeException('test')
        ])->instantiate(FakeClassUsingContexts::class);

        self::assertInstanceOf(FakeClassUsingContexts::class, $instance);
        self::assertSame($logicException, $instance->throwable);
        self::assertSame('test', $instance->runtimeException->getMessage());
    }

    public function testInstantiate_ConstructorWithDefaultValues(): void
    {
        $injector = $this->create();

        self::assertInstanceOf(Exception::class, $injector->instantiate(Exception::class));
    }

    public function testInstantiate_Exception_ClassMissing(): void
    {
        $injector = $this->create();

        $this->expectException(DependencyInjectionException::class);
        /**
         * @psalm-suppress ArgumentTypeCoercion,UndefinedClass warns about issue currently under test
         * @phpstan-ignore-next-line warns about issue currently under test
         */
        $injector->instantiate('NonExistentClass');
    }

    public function testInstantiate_Exception_NotInstantiable(): void
    {
        $injector = $this->create();

        $this->expectException(DependencyInjectionException::class);
        $injector->instantiate(FakeAbstractClass::class);
    }

    public function testInstantiate_Exception_MissingArgs(): void
    {
        $injector = $this->create();

        // missing argument of type RuntimeException
        $this->expectException(DependencyInjectionException::class);
        $injector->instantiate(FakeClassUsingContexts::class);
    }

    public function testInstantiate_NamedParamsOverrideContainer(): void
    {
        $injector = $this->create([
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

    public function testInstantiate_PositionalParamsOverrideContainer(): void
    {
        $injector = $this->create([
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

    public function testInstantiate_WithNoConstructor(): void
    {
        $injector = $this->create();
        $instance = $injector
            ->instantiate(FakeClassNoConstructor::class);

        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
    }

    public function testCall(): void
    {
        $logicException = new LogicException('Message 1');
        $runtimeException = new RuntimeException('Message 2');
        $injector = $this->create([
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

    public function testCall_MissingNullableArgInjectsNull(): void
    {
        $injector = $this->create();

        self::assertNull($injector->call(fn (?FakeClassNoConstructor $obj) => $obj));
    }

    public function testCall_Exception_MissingArg(): void
    {
        $injector = $this->create();

        $this->expectException(DependencyInjectionException::class);
        $injector->call(fn (FakeClassNoConstructor $obj) => $obj);
    }

    public function testCall_UnionType_First(): void
    {
        $instance = new FakeClassImplementsInterfaces();
        self::assertSame(
            $instance,
            $this->create([
                FakeInterfaceOne::class => fn () => $instance
            ])->call(fn (FakeInterfaceOne|string|FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_UnionType_Third(): void
    {
        $instance = new FakeClassImplementsInterfaces();
        self::assertSame(
            $instance,
            $this->create([
                FakeInterfaceTwo::class => fn () => $instance
            ])->call(fn (FakeInterfaceOne|string|FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_Exception_NoMatchInUnionType(): void
    {
        $injector =
            $this->create([FakeClassImplementsInterfaces::class => fn () => new FakeClassImplementsInterfaces()]);

        self::assertUnresolvedParameterException(
            'Closure::__invoke',
            'obj',
            FakeInterfaceOne::class . '|' . FakeInterfaceTwo::class,
            fn () => $injector->call(fn (FakeInterfaceOne|FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_IntersectionType_First(): void
    {
        $instance = new FakeClassImplementsInterfaces();
        self::assertSame(
            $instance,
            $this->create([FakeInterfaceOne::class => fn () => $instance])
                ->call(fn (FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_IntersectionType_Second(): void
    {
        $instance = new FakeClassImplementsInterfaces();
        self::assertSame(
            $instance,
            $this->create([FakeInterfaceTwo::class => fn () => $instance])
                ->call(fn (FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_Exception_MissingOneInIntersectionType(): void
    {
        $injector = $this->create([FakeInterfaceOne::class => fn () => new FakeClassImplementsInterfaces()]);

        self::assertUnresolvedParameterException(
            'Closure::__invoke',
            'obj',
            FakeInterfaceOne::class . '&' . FakeInterfaceThree::class,
            fn () => $injector->call(fn (FakeInterfaceOne&FakeInterfaceThree $obj) => $obj)
        );
    }

    public function testCall_Exception_CircularDependency(): void
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
