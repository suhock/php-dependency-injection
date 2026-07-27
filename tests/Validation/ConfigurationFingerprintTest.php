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
use Suhock\DependencyInjection\Builder\Descriptor;
use Suhock\DependencyInjection\Fakes\FakeBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\Fakes\FakeUnitEnum;
use Suhock\DependencyInjection\InstanceProvider\ClassInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\ClosureInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\ImplementationInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\ObjectInstanceProvider;
use Suhock\DependencyInjection\Key;
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
            $shouldDispose,
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
            new ObjectInstanceProvider($className, $instance),
        );
    }

    /**
     * @param class-string $className
     *
     * @return Descriptor<object>
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    private static function transientClosure(string $className, Closure $factory): Descriptor
    {
        return new Descriptor(
            $className,
            new TransientStrategy($className),
            new ClosureInstanceProvider($className, $factory),
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
            FakeClassNoConstructor::class
                => self::transientClosure(FakeClassNoConstructor::class, self::makeCapturingFactory(1)),
        ];
        $descriptorsB = [
            FakeClassNoConstructor::class
                => self::transientClosure(FakeClassNoConstructor::class, self::makeCapturingFactory(2)),
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
            new FakeClassWithConstructor(new FakeClassNoConstructor()),
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
        $factoryOne = static fn(): FakeClassNoConstructor => new FakeClassNoConstructor();
        $factoryTwo = static fn(): FakeClassNoConstructor => new FakeClassNoConstructor();

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
            FakeClassNoConstructor::class
                => self::transientObject(FakeClassNoConstructor::class, new FakeClassNoConstructor()),
        ];
        $descriptorsB = [
            FakeClassNoConstructor::class
                => self::transientObject(FakeClassNoConstructor::class, new FakeClassNoConstructor()),
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
            FakeClassNoConstructor::class
                => self::transientClosure(FakeClassNoConstructor::class, strlen(...)),
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

    /**
     * @param class-string $className
     *
     * @return Descriptor<object>
     */
    private static function autowireClass(string $className): Descriptor
    {
        return new Descriptor(
            $className,
            new TransientStrategy($className),
            new ClassInstanceProvider($className),
        );
    }

    /**
     * @param class-string $className
     * @param class-string $implementationClassName
     *
     * @return Descriptor<object>
     */
    private static function implementation(string $className, string $implementationClassName): Descriptor
    {
        return new Descriptor(
            $className,
            new TransientStrategy($className),
            new ImplementationInstanceProvider($className, $implementationClassName),
        );
    }

    /**
     * A factory declared at a single, fixed site whose sole parameter carries a string {@see Key}, used to exercise
     * the keyed-parameter branch of the closure signature while keeping the declaration site stable across calls.
     *
     * @return Closure(FakeClassNoConstructor): FakeClassNoConstructor
     */
    private static function makeStringKeyedFactory(): Closure
    {
        return static fn(#[Key('key1')] FakeClassNoConstructor $dependency): FakeClassNoConstructor => $dependency;
    }

    /**
     * As {@see makeStringKeyedFactory()}, but the parameter's {@see Key} is a unit enum, exercising the enum branch of
     * the key signature.
     *
     * @return Closure(FakeClassNoConstructor): FakeClassNoConstructor
     */
    private static function makeEnumKeyedFactory(): Closure
    {
        return static fn(
            #[Key(FakeUnitEnum::Test)]
            FakeClassNoConstructor $dependency,
        ): FakeClassNoConstructor => $dependency;
    }

    public function testCompute_WithSelfParameterAddedToFactory_DiffersFromWithout(): void
    {
        // Arrange: the same factory, one taking the service it produces and one not.
        $withoutSelf = [
            FakeClassNoConstructor::class => self::transientClosure(
                FakeClassNoConstructor::class,
                static fn(): FakeClassNoConstructor => new FakeClassNoConstructor(),
            ),
        ];
        $withSelf = [
            FakeClassNoConstructor::class => self::transientClosure(
                FakeClassNoConstructor::class,
                static fn(FakeClassNoConstructor $self): FakeClassNoConstructor => $self,
            ),
        ];

        // Act
        $digestWithout = ConfigurationFingerprint::compute($withoutSelf);
        $digestWith = ConfigurationFingerprint::compute($withSelf);

        // Assert: the factory's parameter list is part of the fingerprint, so the self parameter changes the digest.
        self::assertNotNull($digestWithout);
        self::assertNotNull($digestWith);
        self::assertNotSame($digestWithout, $digestWith);
    }

    public function testCompute_WithImplementationProvider_DiffersFromAutowireOfSameClass(): void
    {
        // Arrange: the same class provided by reference to a concrete implementation versus by autowiring.
        $byReference = [FakeBaseClass::class => self::implementation(FakeBaseClass::class, FakeClassExtendsBaseClass::class)];
        $byAutowire = [FakeBaseClass::class => self::autowireClass(FakeBaseClass::class)];

        // Act
        $digestReference = ConfigurationFingerprint::compute($byReference);
        $digestAutowire = ConfigurationFingerprint::compute($byAutowire);

        // Assert
        self::assertNotNull($digestReference);
        self::assertNotNull($digestAutowire);
        self::assertNotSame($digestReference, $digestAutowire);
    }

    public function testCompute_WithStringKeyedFactoryParam_ProducesStableDigest(): void
    {
        // Arrange: two factories from the same site whose parameter carries the same string key.
        $descriptorsA = [
            FakeClassNoConstructor::class
                => self::transientClosure(FakeClassNoConstructor::class, self::makeStringKeyedFactory()),
        ];
        $descriptorsB = [
            FakeClassNoConstructor::class
                => self::transientClosure(FakeClassNoConstructor::class, self::makeStringKeyedFactory()),
        ];

        // Act
        $digestA = ConfigurationFingerprint::compute($descriptorsA);
        $digestB = ConfigurationFingerprint::compute($descriptorsB);

        // Assert: keyed parameters are fingerprinted deterministically.
        self::assertNotNull($digestA);
        self::assertSame($digestA, $digestB);
    }

    public function testCompute_WithEnumKeyedFactoryParam_ProducesStableDigest(): void
    {
        // Arrange
        $descriptorsA = [
            FakeClassNoConstructor::class
                => self::transientClosure(FakeClassNoConstructor::class, self::makeEnumKeyedFactory()),
        ];
        $descriptorsB = [
            FakeClassNoConstructor::class
                => self::transientClosure(FakeClassNoConstructor::class, self::makeEnumKeyedFactory()),
        ];

        // Act
        $digestA = ConfigurationFingerprint::compute($descriptorsA);
        $digestB = ConfigurationFingerprint::compute($descriptorsB);

        // Assert
        self::assertNotNull($digestA);
        self::assertSame($digestA, $digestB);
    }
}
