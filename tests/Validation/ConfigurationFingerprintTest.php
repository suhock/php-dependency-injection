<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Validation;

use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Suhock\DependencyInjection\Descriptor\Descriptor;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\InstanceProvider\ClosureInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\ObjectInstanceProvider;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;
use Suhock\DependencyInjection\Lifetime\TransientStrategy;

use function strlen;

/**
 * Test suite for {@see ConfigurationFingerprint}.
 */
final class ConfigurationFingerprintTest extends TestCase
{
    /**
     * @param class-string $className
     *
     * @return Descriptor<object>
     */
    private static function transientObject(string $className, object $instance, bool $shouldDispose = true): Descriptor
    {
        return new Descriptor(
            $className,
            new TransientStrategy($className),
            new ObjectInstanceProvider($className, $instance),
            $shouldDispose
        );
    }

    /**
     * @param class-string $className
     *
     * @return Descriptor<object>
     */
    private static function singletonObject(string $className, object $instance): Descriptor
    {
        return new Descriptor(
            $className,
            new SingletonStrategy($className),
            new ObjectInstanceProvider($className, $instance)
        );
    }

    /**
     * @param class-string $className
     *
     * @return Descriptor<object>
     */
    private static function transientClosure(string $className, Closure $factory): Descriptor
    {
        return new Descriptor(
            $className,
            new TransientStrategy($className),
            new ClosureInstanceProvider($className, $factory)
        );
    }

    /**
     * Produces a closure at a single, fixed definition site, capturing a different value on each call so callers get
     * distinct closure objects that nonetheless share a file/line/signature identity.
     *
     * @return Closure(): FakeClassNoConstructor
     */
    private static function makeCapturingFactory(int $capturedValue): Closure
    {
        return function () use ($capturedValue): FakeClassNoConstructor {
            if ($capturedValue < 0) {
                throw new RuntimeException('The captured value must not be negative');
            }

            return new FakeClassNoConstructor();
        };
    }

    public function testCompute_WithClosuresFromSameSiteButDifferentCapturedValues_ProducesIdenticalFingerprints(): void
    {
        // Arrange
        $descriptorsA = [
            FakeClassNoConstructor::class =>
                self::transientClosure(FakeClassNoConstructor::class, self::makeCapturingFactory(1)),
        ];
        $descriptorsB = [
            FakeClassNoConstructor::class =>
                self::transientClosure(FakeClassNoConstructor::class, self::makeCapturingFactory(2)),
        ];

        // Act
        $digestA = ConfigurationFingerprint::compute($descriptorsA);
        $digestB = ConfigurationFingerprint::compute($descriptorsB);

        // Assert
        self::assertNotNull($digestA);
        self::assertSame($digestA, $digestB);
    }

    public function testCompute_WithReversedDescriptorInsertionOrder_ProducesIdenticalFingerprints(): void
    {
        // Arrange
        $descriptorOne = self::transientObject(FakeClassNoConstructor::class, new FakeClassNoConstructor());
        $descriptorTwo = self::transientObject(
            FakeInterfaceOne::class,
            new FakeClassWithConstructor(new FakeClassNoConstructor())
        );

        $forwardOrder = [
            FakeClassNoConstructor::class => $descriptorOne,
            FakeInterfaceOne::class => $descriptorTwo,
        ];
        $reversedOrder = [
            FakeInterfaceOne::class => $descriptorTwo,
            FakeClassNoConstructor::class => $descriptorOne,
        ];

        // Act
        $digestForward = ConfigurationFingerprint::compute($forwardOrder);
        $digestReversed = ConfigurationFingerprint::compute($reversedOrder);

        // Assert
        self::assertNotNull($digestForward);
        self::assertSame($digestForward, $digestReversed);
    }

    public function testCompute_WithFactoryClosuresOnDifferentLines_ProducesDifferentFingerprints(): void
    {
        // Arrange
        $factoryOne = static fn (): FakeClassNoConstructor => new FakeClassNoConstructor();
        $factoryTwo = static fn (): FakeClassNoConstructor => new FakeClassNoConstructor();

        $descriptorsA = [
            FakeClassNoConstructor::class => self::transientClosure(FakeClassNoConstructor::class, $factoryOne),
        ];
        $descriptorsB = [
            FakeClassNoConstructor::class => self::transientClosure(FakeClassNoConstructor::class, $factoryTwo),
        ];

        // Act
        $digestA = ConfigurationFingerprint::compute($descriptorsA);
        $digestB = ConfigurationFingerprint::compute($descriptorsB);

        // Assert
        self::assertNotNull($digestA);
        self::assertNotNull($digestB);
        self::assertNotSame($digestA, $digestB);
    }

    public function testCompute_WithLifetimeStrategyClassChanged_ProducesDifferentFingerprints(): void
    {
        // Arrange
        $instance = new FakeClassNoConstructor();

        $transient = [
            FakeClassNoConstructor::class => self::transientObject(FakeClassNoConstructor::class, $instance),
        ];
        $singleton = [
            FakeClassNoConstructor::class => self::singletonObject(FakeClassNoConstructor::class, $instance),
        ];

        // Act
        $digestTransient = ConfigurationFingerprint::compute($transient);
        $digestSingleton = ConfigurationFingerprint::compute($singleton);

        // Assert
        self::assertNotNull($digestTransient);
        self::assertNotSame($digestTransient, $digestSingleton);
    }

    public function testCompute_WithDifferentInstancesOfSameClass_ProducesIdenticalFingerprints(): void
    {
        // Arrange
        $descriptorsA = [
            FakeClassNoConstructor::class =>
                self::transientObject(FakeClassNoConstructor::class, new FakeClassNoConstructor()),
        ];
        $descriptorsB = [
            FakeClassNoConstructor::class =>
                self::transientObject(FakeClassNoConstructor::class, new FakeClassNoConstructor()),
        ];

        // Act
        $digestA = ConfigurationFingerprint::compute($descriptorsA);
        $digestB = ConfigurationFingerprint::compute($descriptorsB);

        // Assert
        self::assertNotNull($digestA);
        self::assertSame($digestA, $digestB);
    }

    public function testCompute_WithDescriptorClassNameChanged_ProducesDifferentFingerprints(): void
    {
        // Arrange
        $instance = new FakeClassWithConstructor(new FakeClassNoConstructor());

        $descriptorsA = [
            'sameId' => self::transientObject(FakeInterfaceOne::class, $instance),
        ];
        $descriptorsB = [
            'sameId' => self::transientObject(FakeClassWithConstructor::class, $instance),
        ];

        // Act
        $digestA = ConfigurationFingerprint::compute($descriptorsA);
        $digestB = ConfigurationFingerprint::compute($descriptorsB);

        // Assert
        self::assertNotNull($digestA);
        self::assertNotSame($digestA, $digestB);
    }

    public function testCompute_WithFactoryFromInternalFunction_ReturnsNull(): void
    {
        // Arrange
        $descriptors = [
            FakeClassNoConstructor::class =>
                self::transientClosure(FakeClassNoConstructor::class, strlen(...)),
        ];

        // Act
        $digest = ConfigurationFingerprint::compute($descriptors);

        // Assert
        self::assertNull($digest);
    }

    public function testCompute_WithShouldDisposeFlipped_ProducesDifferentFingerprints(): void
    {
        // Arrange
        $instance = new FakeClassNoConstructor();

        $withDisposal = [
            FakeClassNoConstructor::class => self::transientObject(FakeClassNoConstructor::class, $instance, true),
        ];
        $withoutDisposal = [
            FakeClassNoConstructor::class => self::transientObject(FakeClassNoConstructor::class, $instance, false),
        ];

        // Act
        $digestWithDisposal = ConfigurationFingerprint::compute($withDisposal);
        $digestWithoutDisposal = ConfigurationFingerprint::compute($withoutDisposal);

        // Assert
        self::assertNotNull($digestWithDisposal);
        self::assertNotSame($digestWithDisposal, $digestWithoutDisposal);
    }
}
