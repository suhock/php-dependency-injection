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
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Test suite for {@see Injector}.
 */
class InjectorTest extends TestCase
{
    /**
     * @return array{Injector, FakeContainer}
     */
    protected function create(): array
    {
        $container = new FakeContainer();
        $injector = new Injector($container);

        return [$injector, $container];
    }

    public function testInstantiate(): void
    {
        [$injector, $container] = $this->create();

        $logicException = new LogicException();
        $container->classMapping = [
            Throwable::class => $logicException,
            LogicException::class => $logicException,
            RuntimeException::class => new RuntimeException()
        ];

        $instance = $injector->instantiate(FakeClassUsingContexts::class);
        self::assertInstanceOf(FakeClassUsingContexts::class, $instance);
        self::assertSame($container->classMapping[Throwable::class], $instance->throwable);
        self::assertSame($container->classMapping[RuntimeException::class], $instance->runtimeException);
    }

    public function testInstantiate_MissingClass(): void
    {
        [$injector] = $this->create();

        self::expectException(DependencyInjectionException::class);
        /**
         * @psalm-suppress ArgumentTypeCoercion,UndefinedClass warns about issue under test
         * @phpstan-ignore-next-line warns about issue under test
         */
        $injector->instantiate('NoSuchClass');
    }

    public function testInstantiate_NotInstantiable(): void
    {
        [$injector] = $this->create();

        self::expectException(DependencyInjectionException::class);
        $injector->instantiate(FakeAbstractClass::class);
    }

    public function testInstantiate_DefaultParameterValues(): void
    {
        [$injector] = $this->create();

        self::assertInstanceOf(Exception::class, $injector->instantiate(Exception::class));
    }

    public function testInstantiate_MissingArgs(): void
    {
        [$injector, $container] = $this->create();

        $logicException = new LogicException();
        $container->classMapping = [
            Throwable::class => $logicException,
            LogicException::class => $logicException
        ];

        self::expectException(DependencyInjectionException::class);
        $injector->instantiate(FakeClassUsingContexts::class);
    }

    public function testInstantiate_ExplicitArgs(): void
    {
        [$injector, $container] = $this->create();

        $container->classMapping = [
            Throwable::class => new LogicException(),
            RuntimeException::class => new RuntimeException()
        ];

        self::assertSame(
            $override = new Exception(),
            $injector->instantiate(FakeClassUsingContexts::class, [
                'throwable' => $override
            ])->throwable
        );
    }

    public function testInstantiate_ExplicitArgsPositional(): void
    {
        [$injector, $container] = $this->create();

        $container->classMapping = [
            Throwable::class => new LogicException(),
            RuntimeException::class => new RuntimeException()
        ];

        self::assertSame(
            $override = new RuntimeException(),
            $injector->instantiate(FakeClassUsingContexts::class, [
                1 => $override
            ])->runtimeException
        );
    }

    public function testInstantiate_noConstructor(): void
    {
        [$injector] = $this->create();

        $instance = $injector->instantiate(FakeClassNoConstructor::class);
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
    }

    public function testCall(): void
    {
        [$injector, $container] = $this->create();

        $logicException = new LogicException('Message 1');
        $container->classMapping = [
            Throwable::class => $logicException,
            LogicException::class => $logicException,
            RuntimeException::class => new RuntimeException('Message 2')
        ];

        self::assertSame(
            [$container->classMapping[Throwable::class], $container->classMapping[RuntimeException::class]],
            /** @phpstan-ignore-next-line PHPStan does not understand splat in parameter lists */
            $injector->call(fn (Throwable $e1, RuntimeException $e2) => [$e1, $e2])
        );
    }

    public function testCall_MissingArgs(): void
    {
        [$injector] = $this->create();

        self::expectException(DependencyInjectionException::class);
        /** @phpstan-ignore-next-line PHPStan does not understand splat in parameter lists */
        $injector->call(fn (Throwable $e1, RuntimeException $e2) => [$e1, $e2]);
    }

    public function testCall_UnionType_First(): void
    {
        [$injector, $container] = $this->create();

        $container->classMapping[FakeInterfaceOne::class] = new FakeClassImplementsInterfaces();

        self::assertSame(
            $container->classMapping[FakeInterfaceOne::class],
            $injector->call(fn (FakeInterfaceOne|string|FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_UnionType_Third(): void
    {
        [$injector, $container] = $this->create();

        $container->classMapping[FakeInterfaceTwo::class] = new FakeClassImplementsInterfaces();

        self::assertSame(
            $container->classMapping[FakeInterfaceTwo::class],
            $injector->call(fn (FakeInterfaceOne|string|FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_UnionType_NoMatch(): void
    {
        [$injector, $container] = $this->create();

        $container->classMapping[FakeClassImplementsInterfaces::class] = new FakeClassImplementsInterfaces();

        self::expectException(UnresolvedParameterException::class);
        $injector->call(fn (FakeInterfaceOne|FakeInterfaceTwo $obj) => $obj);
    }

    public function testCall_IntersectionType_First(): void
    {
        [$injector, $container] = $this->create();

        $container->classMapping[FakeInterfaceOne::class] = new FakeClassImplementsInterfaces();

        self::assertSame(
            $container->classMapping[FakeInterfaceOne::class],
            $injector->call(fn (FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_IntersectionType_Second(): void
    {
        [$injector, $container] = $this->create();

        $container->classMapping[FakeInterfaceTwo::class] = new FakeClassImplementsInterfaces();

        self::assertSame(
            $container->classMapping[FakeInterfaceTwo::class],
            $injector->call(fn (FakeInterfaceOne&FakeInterfaceTwo $obj) => $obj)
        );
    }

    public function testCall_IntersectionType_MissingOne(): void
    {
        [$injector, $container] = $this->create();

        $container->classMapping[FakeInterfaceOne::class] = new FakeClassImplementsInterfaces();

        self::expectException(UnresolvedParameterException::class);
        $injector->call(fn (FakeInterfaceOne&FakeInterfaceThree $obj) => $obj);
    }
}
