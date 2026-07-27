<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Suhock\DependencyInjection\Compiler\ResolutionPlanFactory;
use Suhock\DependencyInjection\InstanceProvider\ImplementationException;
use Suhock\DependencyInjection\InstanceProvider\InstanceTypeException;
use Suhock\DependencyInjection\Lifetime\InstanceStore;
use Throwable;

/**
 * Base class for test cases in the Dependency Injection Test Suite
 */
abstract class AbstractDependencyInjectionTestCase extends TestCase
{
    final protected static function createBuilder(): ContainerBuilder
    {
        return ContainerBuilder::createDefault();
    }

    /**
     * Builds a container directly from hand-assembled descriptors, bypassing the builder, for tests that exercise
     * engine behavior the public configuration surface does not express (e.g. context-derived providers, which only
     * auto-binding constructs).
     *
     * @param array<string, Descriptor<object>> $descriptors
     */
    final protected static function buildRawContainer(array $descriptors): Container
    {
        return new Container($descriptors, (new ResolutionPlanFactory())->compile($descriptors));
    }

    /**
     * Builds a validated container from a configuration callback, the standard arrangement step for tests that
     * exercise the built product.
     *
     * @param callable(ContainerBuilder):mixed $configure
     */
    final protected static function buildContainer(callable $configure): Container
    {
        $builder = ContainerBuilder::createDefault();
        $configure($builder);

        return $builder->build();
    }

    /**
     * Creates a root resolution context for exercising lifetime strategies and instance providers directly.
     */
    final protected static function createResolutionContext(?ContainerInterface $container = null): ResolutionContext
    {
        return new ResolutionContext(
            $container ?? self::createStub(ContainerInterface::class),
            new InstanceStore(),
        );
    }

    /**
     * Creates a scope resolution context whose root is the given context, for exercising lifetime strategies directly.
     */
    final protected static function createScopeResolutionContext(ResolutionContext $rootContext): ResolutionContext
    {
        return new ResolutionContext(
            self::createStub(ContainerInterface::class),
            new InstanceStore(),
            $rootContext,
        );
    }

    /**
     * @template TClass of Throwable
     *
     * @param class-string<TClass> $expectedException
     * @param callable(TClass):void $exceptionTest
     * @param callable():mixed $codeUnderTest
     */
    private static function assertThrowsThrowable(
        string $expectedException,
        callable $exceptionTest,
        callable $codeUnderTest,
    ): void {
        try {
            $codeUnderTest();
        } catch (Throwable $exception) {
            self::assertInstanceOf($expectedException, $exception, $exception->getMessage());
            /** @var TClass $exception */
            $exceptionTest($exception);

            return;
        }

        Assert::fail('Exception was not thrown');
    }

    /**
     * @param class-string $exClassName
     * @param callable():mixed $codeUnderTest
     */
    final public static function assertThrowsCircularDependencyException(
        string $exClassName,
        callable $codeUnderTest,
    ): void {
        self::assertThrowsThrowable(
            CircularDependencyException::class,
            static fn(CircularDependencyException $exception) => self::assertCircularDependencyException(
                $exClassName,
                $exception,
            ),
            $codeUnderTest,
        );
    }

    /**
     * @param class-string $exClassName
     * @param CircularDependencyException<object> $actualException
     */
    final public static function assertCircularDependencyException(
        string $exClassName,
        CircularDependencyException $actualException,
    ): void {
        self::assertSame(
            $exClassName,
            $actualException->getClassName(),
            'Failed asserting that class name is identical',
        );
    }

    /**
     * @param class-string $exExpectedClassName
     * @param class-string $exActualClassName
     * @param callable():mixed $codeUnderTest
     */
    final public static function assertThrowsImplementationException(
        string $exExpectedClassName,
        string $exActualClassName,
        callable $codeUnderTest,
    ): void {
        self::assertThrowsThrowable(
            ImplementationException::class,
            static fn(ImplementationException $exception) => self::assertImplementationException(
                $exExpectedClassName,
                $exActualClassName,
                $exception,
            ),
            $codeUnderTest,
        );
    }

    /**
     * @template TExpected of object
     * @template TActual of object
     *
     * @param class-string<TExpected> $exExpectedClassName
     * @param class-string<TActual> $exActualClassName
     * @param ImplementationException<TExpected, TActual> $actualException
     */
    final public static function assertImplementationException(
        string $exExpectedClassName,
        string $exActualClassName,
        ImplementationException $actualException,
    ): void {
        self::assertSame(
            $exExpectedClassName,
            $actualException->getExpectedClassName(),
            'Failed asserting that expected class name is identical',
        );
        self::assertSame(
            $exActualClassName,
            $actualException->getActualClassName(),
            'Failed asserting that actual class name is identical',
        );
    }

    /**
     * @param class-string $exExpectedClassName
     * @param class-string|null $exActualClassName
     * @param callable():mixed $codeUnderTest
     */
    final public static function assertThrowsInstanceTypeException(
        string $exExpectedClassName,
        ?string $exActualClassName,
        callable $codeUnderTest,
    ): void {
        self::assertThrowsThrowable(
            InstanceTypeException::class,
            static fn(InstanceTypeException $exception) => self::assertInstanceTypeException(
                $exExpectedClassName,
                $exActualClassName,
                $exception,
            ),
            $codeUnderTest,
        );
    }

    /**
     * @param class-string $exExpectedClassName
     * @param class-string|null $exActualClassName
     * @param InstanceTypeException<object> $actualException
     */
    final public static function assertInstanceTypeException(
        string $exExpectedClassName,
        ?string $exActualClassName,
        InstanceTypeException $actualException,
    ): void {
        self::assertSame(
            $exExpectedClassName,
            $actualException->getExpectedClassName(),
            'Failed asserting that expected class name is identical',
        );

        if ($exActualClassName !== null) {
            self::assertInstanceOf(
                $exActualClassName,
                $actualException->getActualValue(),
                'Failed asserting that actual value is of the correct type',
            );
        } else {
            self::assertNull(
                $actualException->getActualValue(),
                'Failed asserting that actual value is null',
            );
        }
    }

    /**
     * @param class-string $expectedClassName
     * @param callable():mixed $codeUnderTest
     */
    final public static function assertThrowsClassNotFoundException(
        string $expectedClassName,
        callable $codeUnderTest,
    ): void {
        self::assertThrowsThrowable(
            ClassNotFoundException::class,
            static fn(ClassNotFoundException $exception) => self::assertClassNotFoundException(
                $expectedClassName,
                $exception,
            ),
            $codeUnderTest,
        );
    }

    /**
     * @param class-string $expectedClassName
     * @param ClassNotFoundException<object> $actualException
     */
    final public static function assertClassNotFoundException(
        string $expectedClassName,
        ClassNotFoundException $actualException,
    ): void {
        self::assertSame(
            $expectedClassName,
            $actualException->getClassName(),
            'Failed asserting that class name is identical',
        );
    }

    /**
     * @template TException of DependencyInjectionExceptionInterface
     *
     * @param class-string $expectedClassName
     * @param (callable(TException):void)|null $previousExceptionTest
     * @param callable():mixed $codeUnderTest
     */
    final public static function assertThrowsClassResolutionException(
        string $expectedClassName,
        ?callable $previousExceptionTest,
        callable $codeUnderTest,
    ): void {
        self::assertThrowsThrowable(
            ClassResolutionException::class,
            static fn(ClassResolutionException $exception) => self::assertClassResolutionException(
                $expectedClassName,
                $previousExceptionTest,
                $exception,
            ),
            $codeUnderTest,
        );
    }

    /**
     * @template TException of DependencyInjectionExceptionInterface
     *
     * @param class-string $expectedClassName
     * @param ClassResolutionException<object> $actualException
     * @param (callable(TException):void)|null $previousExceptionTest
     */
    final public static function assertClassResolutionException(
        string $expectedClassName,
        ?callable $previousExceptionTest,
        ClassResolutionException $actualException,
    ): void {
        self::assertSame(
            $expectedClassName,
            $actualException->getClassName(),
            'Failed asserting that class name is identical',
        );

        if ($previousExceptionTest !== null) {
            // @phpstan-ignore argument.type (the callback asserts the concrete exception type it expects)
            $previousExceptionTest($actualException->getConsolidatedException());
        }
    }

    /**
     * @template TException of DependencyInjectionExceptionInterface
     *
     * @param (callable(TException):void)|null $previousTest
     * @param callable():mixed $codeUnderTest
     */
    final public static function assertThrowsParameterResolutionException(
        string $exFunctionName,
        string $exParameterName,
        ?callable $previousTest,
        callable $codeUnderTest,
    ): void {
        self::assertThrowsThrowable(
            ParameterResolutionException::class,
            static fn(ParameterResolutionException $exception) => self::assertParameterResolutionException(
                $exFunctionName,
                $exParameterName,
                $previousTest,
                $exception,
            ),
            $codeUnderTest,
        );
    }

    /**
     * @template TException of DependencyInjectionExceptionInterface
     *
     * @param (callable(TException):void)|null $previousTest
     */
    final public static function assertParameterResolutionException(
        string $exFunctionName,
        string $exParameterName,
        ?callable $previousTest,
        ParameterResolutionException $actualException,
    ): void {
        $actualFunctionName = $actualException->getReflectionParameter()
            ->getDeclaringFunction()
            ->getName();

        // Closures generate a runtime-defined, PHP-version-dependent name (e.g. "{closure:File.php:42}" as of
        // PHP 8.4), so only assert that the declaring function is a closure rather than matching an exact name.
        if (str_ends_with($exFunctionName, '{closure}')) {
            self::assertStringContainsString(
                '{closure',
                $actualFunctionName,
                'Failed asserting that function is a closure',
            );
        } else {
            self::assertSame(
                $exFunctionName,
                $actualFunctionName,
                'Failed asserting that function name is identical',
            );
        }
        self::assertSame(
            $exParameterName,
            $actualException->getReflectionParameter()->getName(),
            'Failed asserting that parameter name is identical',
        );

        if ($previousTest !== null) {
            // @phpstan-ignore argument.type (the callback asserts the concrete exception type it expects)
            $previousTest($actualException->getConsolidatedException());
        }
    }
}
