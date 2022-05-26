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

class DependencyInjectorTest extends TestCase
{
    private DependencyInjector $injector;

    public array $classMapping = [];

    protected function setUp(): void
    {
        $this->injector = new DependencyInjector(
            new class($this) implements DependencyContainerInterface
            {
                public function __construct(private readonly DependencyInjectorTest $test)
                {
                }

                public function get(string $className): ?object
                {
                    return $this->has($className) ? $this->test->classMapping[$className] :
                        throw new UnresolvedClassException($className);
                }

                public function has(string $className): bool
                {
                    return array_key_exists($className, $this->test->classMapping);
                }
            }
        );
    }

    public function testInstantiate(): void
    {
        $logicException = new LogicException();
        $this->classMapping = [
            Throwable::class => $logicException,
            LogicException::class => $logicException,
            RuntimeException::class => new RuntimeException()
        ];

        $instance = $this->injector->instantiate(ConstructorTestClass::class);
        self::assertInstanceOf(ConstructorTestClass::class, $instance);
        self::assertSame($this->classMapping[Throwable::class], $instance->throwable);
        self::assertSame($this->classMapping[RuntimeException::class], $instance->runtimeException);
    }

    public function testInstantiate_MissingClass(): void
    {
        self::expectException(DependencyInjectionException::class);
        /** @psalm-suppress ArgumentTypeCoercion,UndefinedClass */
        $this->injector->instantiate('NoSuchClass');
    }

    public function testInstantiate_NotInstantiable(): void
    {
        self::expectException(DependencyInjectionException::class);
        $this->injector->instantiate(AbstractTestClass::class);
    }

    public function testInstantiate_DefaultParameterValues(): void
    {
        self::assertInstanceOf(Exception::class, $this->injector->instantiate(Exception::class));
    }

    public function testInstantiate_MissingArgs(): void
    {
        $logicException = new LogicException();
        $this->classMapping = [
            Throwable::class => $logicException,
            LogicException::class => $logicException
        ];

        self::expectException(DependencyInjectionException::class);
        $this->injector->instantiate(ConstructorTestClass::class);
    }

    public function testInstantiate_ExplicitArgs(): void
    {
        $this->classMapping = [
            Throwable::class => new LogicException(),
            RuntimeException::class => new RuntimeException()
        ];

        self::assertSame(
            $override = new Exception(),
            $this->injector->instantiate(ConstructorTestClass::class, [
                'throwable' => $override
            ])->throwable
        );
    }

    public function testInstantiate_ExplicitArgsPositional(): void
    {
        $this->classMapping = [
            Throwable::class => new LogicException(),
            RuntimeException::class => new RuntimeException()
        ];

        self::assertSame(
            $override = new RuntimeException(),
            $this->injector->instantiate(ConstructorTestClass::class, [
                1 => $override
            ])->runtimeException
        );
    }

    public function testInstantiate_noConstructor(): void
    {
        $instance = $this->injector->instantiate(NoConstructorTestClass::class);
        self::assertInstanceOf(NoConstructorTestClass::class, $instance);
    }

    public function testCall(): void
    {
        $logicException = new LogicException("Message 1");
        $this->classMapping = [
            Throwable::class => $logicException,
            LogicException::class => $logicException,
            RuntimeException::class => new RuntimeException("Message 2")
        ];

        self::assertSame(
            [$this->classMapping[Throwable::class], $this->classMapping[RuntimeException::class]],
            $this->injector->call(fn (Throwable $e1, RuntimeException $e2) => [$e1, $e2])
        );
    }

    public function testCall_MissingArgs(): void
    {
        self::expectException(DependencyInjectionException::class);
        $this->injector->call(fn (Throwable $e1, RuntimeException $e2) => [$e1, $e2]);
    }
}
