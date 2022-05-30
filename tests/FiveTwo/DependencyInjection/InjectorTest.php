<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Exception;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

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

        $instance = $injector->instantiate(FakeContextAwareClass::class);
        self::assertInstanceOf(FakeContextAwareClass::class, $instance);
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
        $injector->instantiate(FakeContextAwareClass::class);
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
            $injector->instantiate(FakeContextAwareClass::class, [
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
            $injector->instantiate(FakeContextAwareClass::class, [
                1 => $override
            ])->runtimeException
        );
    }

    public function testInstantiate_noConstructor(): void
    {
        [$injector] = $this->create();

        $instance = $injector->instantiate(FakeNoConstructorClass::class);
        self::assertInstanceOf(FakeNoConstructorClass::class, $instance);
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
}
