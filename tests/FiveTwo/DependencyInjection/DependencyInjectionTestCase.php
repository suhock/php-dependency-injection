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

use FiveTwo\DependencyInjection\Provision\ImplementationException;
use FiveTwo\DependencyInjection\Provision\InstanceTypeException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Base class for test cases in the Dependency Injection Test Suite
 */
class DependencyInjectionTestCase extends TestCase
{
    /**
     * @template T of Throwable
     *
     * @param class-string<T> $expectedException
     * @param callable(T):void $exceptionTest
     * @param callable $codeUnderTest
     *
     * @return void
     */
    private static function assertThrowsThrowable(
        string $expectedException,
        callable $exceptionTest,
        callable $codeUnderTest
    ): void {
        try {
            $codeUnderTest();
            Assert::fail('Exception was not thrown');
        } catch (AssertionFailedError $e) {
            throw $e;
        } catch (Throwable $exception) {
            self::assertInstanceOf($expectedException, $exception, $exception->getMessage());
            /** @var T $exception */
            $exceptionTest($exception);
        }
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $exClassName
     */
    public static function assertThrowsCircularDependencyException(
        string $exClassName,
        callable $codeUnderTest
    ): void {
        self::assertThrowsThrowable(
            CircularDependencyException::class,
            static fn (CircularDependencyException $exception) => self::assertCircularDependencyException(
                $exClassName,
                $exception
            ),
            $codeUnderTest
        );
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $exClassName
     * @param CircularDependencyException<TClass> $actualException
     */
    public static function assertCircularDependencyException(
        string $exClassName,
        CircularDependencyException $actualException
    ): void {
        self::assertSame(
            $exClassName,
            $actualException->getClassName(),
            'Failed asserting that class name is identical'
        );
    }

    /**
     * @template TExpected of object
     * @template TActual of object
     * @param class-string<TExpected> $exExpectedClassName
     * @param class-string<TActual> $exActualClassName
     */
    public static function assertThrowsImplementationException(
        string $exExpectedClassName,
        string $exActualClassName,
        callable $codeUnderTest
    ): void {
        self::assertThrowsThrowable(
            ImplementationException::class,
            static fn (ImplementationException $exception) => self::assertImplementationException(
                $exExpectedClassName,
                $exActualClassName,
                $exception
            ),
            $codeUnderTest
        );
    }

    /**
     * @template TExpected of object
     * @template TActual of object
     * @param class-string<TExpected> $exExpectedClassName
     * @param class-string<TActual> $exActualClassName
     * @param ImplementationException<TExpected, TActual> $actualException
     */
    public static function assertImplementationException(
        string $exExpectedClassName,
        string $exActualClassName,
        ImplementationException $actualException
    ): void {
        self::assertSame(
            $exExpectedClassName,
            $actualException->getExpectedClassName(),
            'Failed asserting that expected class name is identical'
        );
        self::assertSame(
            $exActualClassName,
            $actualException->getActualClassName(),
            'Failed asserting that actual class name is identical'
        );
    }

    /**
     * @template TExpected of object
     * @template TActual of object
     * @param class-string<TExpected> $exExpectedClassName
     * @param class-string<TActual>|null $exActualClassName
     */
    public static function assertThrowsInstanceTypeException(
        string $exExpectedClassName,
        ?string $exActualClassName,
        callable $codeUnderTest
    ): void {
        self::assertThrowsThrowable(
            InstanceTypeException::class,
            static fn (InstanceTypeException $exception) => self::assertInstanceTypeException(
                $exExpectedClassName,
                $exActualClassName,
                $exception
            ),
            $codeUnderTest
        );
    }

    /**
     * @template TExpected of object
     * @param class-string<TExpected> $exExpectedClassName
     * @param class-string|null $exActualClassName
     * @param InstanceTypeException<TExpected> $actualException
     */
    public static function assertInstanceTypeException(
        string $exExpectedClassName,
        ?string $exActualClassName,
        InstanceTypeException $actualException
    ): void {
        self::assertSame(
            $exExpectedClassName,
            $actualException->getExpectedClassName(),
            'Failed asserting that expected class name is identical'
        );

        if ($exActualClassName !== null) {
            self::assertInstanceOf(
                $exActualClassName,
                $actualException->getActualValue(),
                'Failed asserting that actual value is of the correct type'
            );
        } else {
            self::assertNull(
                $actualException->getActualValue(),
                'Failed asserting that actual value is null'
            );
        }
    }

    /**
     * @template TExpected of object
     * @param class-string<TExpected> $expectedClassName
     */
    public static function assertThrowsClassNotFoundException(
        string $expectedClassName,
        callable $codeUnderTest
    ): void {
        self::assertThrowsThrowable(
            ClassNotFoundException::class,
            static fn (ClassNotFoundException $exception) => self::assertClassNotFoundException(
                $expectedClassName,
                $exception
            ),
            $codeUnderTest
        );
    }

    /**
     * @template TExpected of object
     *
     * @param class-string<TExpected> $expectedClassName
     * @param ClassNotFoundException<TExpected> $actualException
     *
     * @return void
     */
    public static function assertClassNotFoundException(
        string $expectedClassName,
        ClassNotFoundException $actualException
    ): void {
        self::assertSame(
            $expectedClassName,
            $actualException->getClassName(),
            'Failed asserting that class name is identical'
        );
    }

    /**
     * @template TExpected of object
     * @param class-string<TExpected> $expectedClassName
     */
    public static function assertThrowsClassResolutionException(
        string $expectedClassName,
        ?callable $previousExceptionTest,
        callable $codeUnderTest
    ): void {
        self::assertThrowsThrowable(
            ClassResolutionException::class,
            static fn (ClassResolutionException $exception) => self::assertClassResolutionException(
                $expectedClassName,
                $previousExceptionTest,
                $exception
            ),
            $codeUnderTest
        );
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $expectedClassName
     * @param callable|null $previousExceptionTest
     * @param ClassResolutionException<TClass> $actualException
     *
     * @return void
     */
    public static function assertClassResolutionException(
        string $expectedClassName,
        ?callable $previousExceptionTest,
        ClassResolutionException $actualException
    ): void {
        self::assertSame(
            $expectedClassName,
            $actualException->getClassName(),
            'Failed asserting that class name is identical'
        );

        if ($previousExceptionTest) {
            $previousExceptionTest($actualException->getConsolidatedException());
        }
    }

    public static function assertThrowsParameterResolutionException(
        string $exFunctionName,
        string $exParameterName,
        ?callable $previousTest,
        callable $codeUnderTest
    ): void {
        self::assertThrowsThrowable(
            ParameterResolutionException::class,
            static fn (ParameterResolutionException $exception) => self::assertParameterResolutionException(
                $exFunctionName,
                $exParameterName,
                $previousTest,
                $exception
            ),
            $codeUnderTest
        );
    }

    public static function assertParameterResolutionException(
        string $exFunctionName,
        string $exParameterName,
        ?callable $previousTest,
        ParameterResolutionException $actualException
    ): void {
        self::assertSame(
            $exFunctionName,
            $actualException->getReflectionParameter()->getDeclaringFunction()->getName(),
            'Failed asserting that function name is identical'
        );
        self::assertSame(
            $exParameterName,
            $actualException->getReflectionParameter()->getName(),
            'Failed asserting that parameter name is identical'
        );

        if ($previousTest !== null) {
            $previousTest($actualException->getConsolidatedException());
        }
    }
}
