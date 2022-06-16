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
     * @param callable $codeUnderTest
     * @param callable(T):void $test
     *
     * @return void
     */
    public static function assertException(callable $codeUnderTest, callable $test): void
    {
        try {
            $codeUnderTest();
            Assert::fail('Exception was not thrown');
        } catch (AssertionFailedError $e) {
            throw $e;
        } catch (Throwable $exception) {
            /**
             * A TypeError here indicates the wrong type of exception was thrown
             * @phpstan-ignore-next-line
             * @psalm-suppress InvalidArgument
             */
            $test($exception);
        }
    }

    /**
     * @param class-string $exClassName
     * @param string $exContext
     * @param callable $codeUnderTest
     *
     * @return void
     */
    public static function assertCircularDependencyException(
        string $exClassName,
        string $exContext,
        callable $codeUnderTest
    ): void {
        self::assertException(
            $codeUnderTest,
            function (CircularDependencyException $exception) use ($exClassName, $exContext): void {
                self::assertSame(
                    $exClassName,
                    $exception->getClassName(),
                    'Failed asserting that class name is identical'
                );
                self::assertSame(
                    $exContext,
                    $exception->getContext(),
                    'Failed asserting that context is identical'
                );
            }
        );
    }

    /**
     * @param string $exFunctionName
     * @param string $exParameterName
     * @param class-string $exClassName
     * @param callable $codeUnderTest
     *
     * @return void
     */
    public static function assertCircularParameterException(
        string $exFunctionName,
        string $exParameterName,
        string $exClassName,
        callable $codeUnderTest
    ): void {
        self::assertException(
            $codeUnderTest,
            function (CircularParameterException $exception) use (
                $exClassName,
                $exFunctionName,
                $exParameterName
            ): void {
                self::assertSame(
                    $exFunctionName,
                    $exception->getFunctionName(),
                    'Failed asserting that function name is identical'
                );
                self::assertSame(
                    $exParameterName,
                    $exception->getParameterName(),
                    'Failed asserting that parameter name is identical'
                );
                self::assertSame(
                    $exClassName,
                    $exception->getClassName(),
                    'Failed asserting that class name is identical'
                );
            }
        );
    }

    /**
     * @param class-string $exExpectedClassName
     * @param class-string $exActualClassName
     * @param callable $codeUnderTest
     *
     * @return void
     */
    public static function assertImplementationException(
        string $exExpectedClassName,
        string $exActualClassName,
        callable $codeUnderTest
    ): void {
        self::assertException(
            $codeUnderTest,
            function (ImplementationException $exception) use ($exExpectedClassName, $exActualClassName) {
                self::assertSame(
                    $exExpectedClassName,
                    $exception->getExpectedClassName(),
                    'Failed asserting that expected class name is identical'
                );
                self::assertSame(
                    $exActualClassName,
                    $exception->getActualClassName(),
                    'Failed asserting that actual class name is identical'
                );
            }
        );
    }

    /**
     * @param class-string $exExpectedClassName
     * @param class-string $exActualClassName
     * @param callable $codeUnderTest
     *
     * @return void
     */
    public static function assertInstanceTypeException(
        string $exExpectedClassName,
        string $exActualClassName,
        callable $codeUnderTest
    ): void {
        self::assertException(
            $codeUnderTest,
            function (InstanceTypeException $exception) use ($exExpectedClassName, $exActualClassName) {
                self::assertSame(
                    $exExpectedClassName,
                    $exception->getExpectedClassName(),
                    'Failed asserting that expected class name is identical'
                );
                self::assertInstanceOf(
                    $exActualClassName,
                    $exception->getActualValue(),
                    'Failed asserting that type of value is identical'
                );
            }
        );
    }

    /**
     * @param class-string $expectedClassName
     * @param callable $codeUnderTest
     *
     * @return void
     */
    public static function assertUnresolvedClassException(
        string $expectedClassName,
        callable $codeUnderTest
    ): void {
        self::assertException(
            $codeUnderTest,
            function (UnresolvedClassException $exception) use ($expectedClassName) {
                self::assertSame(
                    $expectedClassName,
                    $exception->getClassName(),
                    'Failed asserting that class name is identical'
                );
            }
        );
    }

    /**
     * @param string $exFunctionName
     * @param string $exParameterName
     * @param string $exParameterType
     * @param callable $codeUnderTest
     *
     * @return void
     */
    public static function assertUnresolvedParameterException(
        string $exFunctionName,
        string $exParameterName,
        string $exParameterType,
        callable $codeUnderTest
    ): void {
        self::assertException(
            $codeUnderTest,
            function (UnresolvedParameterException $exception) use (
                $exFunctionName,
                $exParameterName,
                $exParameterType
            ) {
                self::assertSame(
                    $exFunctionName,
                    $exception->getFunctionName(),
                    'Failed asserting that function name is identical'
                );
                self::assertSame(
                    $exParameterName,
                    $exception->getParameterName(),
                    'Failed asserting that parameter name is identical'
                );
                self::assertSame(
                    $exParameterType,
                    $exception->getParameterType(),
                    'Failed asserting that parameter type is identical'
                );
            }
        );
    }
}
