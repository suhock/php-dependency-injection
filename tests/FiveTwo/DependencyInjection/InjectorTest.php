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

    public function testInstantiate_ConstructorWithDefaultValues(): void
    {
        $injector = $this->create();

        self::assertInstanceOf(Exception::class, $injector->instantiate(Exception::class));
    }

    public function testInstantiate_Exception_ClassMissing(): void
    {
        $this->expectException(DependencyInjectionException::class);
        /**
         * @psalm-suppress ArgumentTypeCoercion,UndefinedClass warns about issue currently under test
         * @phpstan-ignore-next-line warns about issue currently under test
         */
        $this->create()->instantiate('NoSuchClass');
    }

    public function testInstantiate_Exception_NotInstantiable(): void
    {
        $this->expectException(DependencyInjectionException::class);
        $this->create()->instantiate(FakeAbstractClass::class);
    }

    public function testInstantiate_Exception_MissingArgs(): void
    {
        // missing argument of type RuntimeException
        $this->expectException(DependencyInjectionException::class);

        $this->create([Throwable::class => fn () => new LogicException()])
            ->instantiate(FakeClassUsingContexts::class);
    }

    public function testInstantiate_NamedParamsOverrideContainer(): void
    {
        self::assertSame(
            $override = new RuntimeException(),
            $this->create([
                Throwable::class => fn () => new LogicException(),
                RuntimeException::class => fn () => new RuntimeException()
            ])->instantiate(FakeClassUsingContexts::class, [
                'runtimeException' => $override
            ])->runtimeException
        );
    }

    public function testInstantiate_PositionalParamsOverrideContainer(): void
    {
        self::assertSame(
            $override = new RuntimeException(),
            $this->create([
                Throwable::class => fn () => new LogicException(),
                RuntimeException::class => fn () => new RuntimeException()
            ])->instantiate(FakeClassUsingContexts::class, [
                1 => $override
            ])->runtimeException
        );
    }

    public function testInstantiate_WithNoConstructor(): void
    {
        $instance = $this->create()
            ->instantiate(FakeClassNoConstructor::class);
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
    }

    public function testCall(): void
    {
        $logicException = new LogicException('Message 1');
        $runtimeException = new RuntimeException('Message 2');

        self::assertSame(
            [$logicException, $runtimeException],
            $this->create([
                Throwable::class => fn () => $logicException,
                LogicException::class => fn () => $logicException,
                RuntimeException::class => fn () => $runtimeException
            /** @phpstan-ignore-next-line PHPStan does not understand splat in parameter lists */
            ])->call(fn (Throwable $e1, RuntimeException $e2) => [$e1, $e2])
        );
    }

    public function testCall_Exception_MissingArgs(): void
    {
        $this->expectException(DependencyInjectionException::class);
        /** @phpstan-ignore-next-line PHPStan does not understand splat in parameter lists */
        $this->create()->call(fn (Throwable $e1, RuntimeException $e2) => [$e1, $e2]);
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
}
