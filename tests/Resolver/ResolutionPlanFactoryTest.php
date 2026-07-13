<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Suhock\DependencyInjection\Builder\Descriptor;
use Suhock\DependencyInjection\Fakes\FakeAbstractClass;
use Suhock\DependencyInjection\Fakes\FakeClassImplementsInterfaces;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithDependencies;
use Suhock\DependencyInjection\Fakes\FakeClassWithInjectedProperties;
use Suhock\DependencyInjection\Fakes\FakeClassWithInjectFunction;
use Suhock\DependencyInjection\Fakes\FakeClassWithIntersectionDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithKeyedDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithStaticInjectMethod;
use Suhock\DependencyInjection\Fakes\FakeClassWithStringDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithUnionDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithVariadicConstructor;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\Fakes\FakeInterfaceTwo;
use Suhock\DependencyInjection\InstanceProvider\ClassInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\ClosureInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\ImplementationInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\InstanceProvider\ObjectInstanceProvider;
use Suhock\DependencyInjection\Lifetime\TransientStrategy;
use Throwable;
use function reset;

/**
 * Test suite for {@see ResolutionPlanFactory}.
 */
final class ResolutionPlanFactoryTest extends TestCase
{
    /**
     * @param class-string $className
     *
     * @return Descriptor<object>
     */
    private static function autowireDescriptor(string $className, ?Closure $mutator = null): Descriptor
    {
        return new Descriptor(
            $className,
            new TransientStrategy($className),
            new ClassInstanceProvider($className, $mutator)
        );
    }

    /**
     * @param class-string $className
     *
     * @return Descriptor<object>
     */
    private static function closureDescriptor(string $className, Closure $factory): Descriptor
    {
        return new Descriptor(
            $className,
            new TransientStrategy($className),
            new ClosureInstanceProvider($className, $factory)
        );
    }

    /**
     * @param class-string $className
     * @param InstanceProviderInterface<object> $provider
     *
     * @return Descriptor<object>
     */
    private static function providerDescriptor(string $className, InstanceProviderInterface $provider): Descriptor
    {
        return new Descriptor($className, new TransientStrategy($className), $provider);
    }

    /**
     * @param class-string $className
     *
     * @return Descriptor<object>
     */
    private static function objectDescriptor(string $className, object $instance): Descriptor
    {
        return self::providerDescriptor($className, new ObjectInstanceProvider($className, $instance));
    }

    /**
     * @param class-string $className
     * @param class-string $implementationClassName
     *
     * @return Descriptor<object>
     */
    private static function implementationDescriptor(string $className, string $implementationClassName): Descriptor
    {
        return self::providerDescriptor(
            $className,
            new ImplementationInstanceProvider($className, $implementationClassName)
        );
    }

    /**
     * @param array<string, Descriptor<object>> $descriptors
     */
    private static function compileSingle(array $descriptors): ResolutionPlan
    {
        $plans = (new ResolutionPlanFactory())->compile($descriptors);
        self::assertCount(1, $plans);
        $plan = reset($plans);

        if ($plan === false) {
            self::fail('No plan was compiled');
        }

        return $plan;
    }

    /**
     * @param list<ResolutionPlanEdge> $edges
     */
    private static function edgeAt(array $edges, int $index): ResolutionPlanEdge
    {
        $edge = $edges[$index] ?? null;

        if ($edge === null) {
            self::fail("No edge at index $index");
        }

        return $edge;
    }

    public function testCompile_WithRequiredConstructorDependencies_ProducesRequiredEdges(): void
    {
        $plan = self::compileSingle([
            FakeClassWithDependencies::class => self::autowireDescriptor(FakeClassWithDependencies::class),
        ]);

        self::assertSame(FakeClassWithDependencies::class, $plan->className);
        self::assertSame(ResolutionPlanKind::AutowiredClass, $plan->kind);
        self::assertNull($plan->nonInstantiableMessage);
        self::assertCount(2, $plan->argumentEdges);
        self::assertSame('throwable', self::edgeAt($plan->argumentEdges, 0)->name);
        self::assertSame([[Throwable::class]], self::edgeAt($plan->argumentEdges, 0)->dependency?->alternatives);
        self::assertSame(
            [[RuntimeException::class]],
            self::edgeAt($plan->argumentEdges, 1)->dependency?->alternatives
        );
        self::assertFalse(self::edgeAt($plan->argumentEdges, 0)->soft);
        self::assertFalse(self::edgeAt($plan->argumentEdges, 1)->soft);
    }

    public function testCompile_WithRequiredBuiltinParameter_ProducesUnconsultableRequiredEdge(): void
    {
        $plan = self::compileSingle([
            FakeClassWithStringDependency::class => self::autowireDescriptor(FakeClassWithStringDependency::class),
        ]);

        self::assertCount(1, $plan->argumentEdges);
        self::assertNull(self::edgeAt($plan->argumentEdges, 0)->dependency);
        self::assertFalse(self::edgeAt($plan->argumentEdges, 0)->soft);
        self::assertSame('string', self::edgeAt($plan->argumentEdges, 0)->declaredType);
    }

    public function testCompile_WithUnionDependency_ProducesAlternativesInDeclaredOrder(): void
    {
        $plan = self::compileSingle([
            FakeClassWithUnionDependency::class => self::autowireDescriptor(FakeClassWithUnionDependency::class),
        ]);

        self::assertSame(
            [[FakeInterfaceOne::class], [FakeInterfaceTwo::class]],
            self::edgeAt($plan->argumentEdges, 0)->dependency?->alternatives
        );
    }

    public function testCompile_WithIntersectionDependency_ProducesSingleConjunction(): void
    {
        $plan = self::compileSingle([
            FakeClassWithIntersectionDependency::class =>
                self::autowireDescriptor(FakeClassWithIntersectionDependency::class),
        ]);

        self::assertSame(
            [[FakeInterfaceOne::class, FakeInterfaceTwo::class]],
            self::edgeAt($plan->argumentEdges, 0)->dependency?->alternatives
        );
    }

    public function testCompile_WithKeyedParameter_CarriesKey(): void
    {
        $plan = self::compileSingle([
            FakeClassWithKeyedDependency::class => self::autowireDescriptor(FakeClassWithKeyedDependency::class),
        ]);

        self::assertSame('key1', self::edgeAt($plan->argumentEdges, 0)->dependency?->key);
    }

    public function testCompile_WithVariadicParameter_ProducesRequiredElementEdge(): void
    {
        $plan = self::compileSingle([
            FakeClassWithVariadicConstructor::class =>
                self::autowireDescriptor(FakeClassWithVariadicConstructor::class),
        ]);

        self::assertCount(1, $plan->argumentEdges);
        self::assertSame(
            [[FakeClassNoConstructor::class]],
            self::edgeAt($plan->argumentEdges, 0)->dependency?->alternatives
        );
        self::assertFalse(self::edgeAt($plan->argumentEdges, 0)->soft);
    }

    public function testCompile_WithDefaultedParameter_CapturesTheDefaultValue(): void
    {
        $plan = self::compileSingle([
            FakeClassNoConstructor::class => self::closureDescriptor(
                FakeClassNoConstructor::class,
                static fn (string $name = 'preset'): FakeClassNoConstructor => new FakeClassNoConstructor()
            ),
        ]);

        $edge = self::edgeAt($plan->argumentEdges, 0);
        self::assertTrue($edge->soft);
        self::assertTrue($edge->hasDefault);
        self::assertSame('preset', $edge->defaultValue);
    }

    public function testCompile_WithInjectMethod_ProducesMethodParameterEdges(): void
    {
        $plan = self::compileSingle([
            FakeClassWithInjectFunction::class => self::autowireDescriptor(FakeClassWithInjectFunction::class),
        ]);

        self::assertArrayHasKey('setObj', $plan->injectMethodEdges);
        $edges = $plan->injectMethodEdges['setObj'] ?? [];
        self::assertCount(1, $edges);
        self::assertSame('obj', self::edgeAt($edges, 0)->name);
        self::assertSame([[FakeClassNoConstructor::class]], self::edgeAt($edges, 0)->dependency?->alternatives);
    }

    public function testCompile_WithInjectedProperties_ProducesPropertyEdges(): void
    {
        $plan = self::compileSingle([
            FakeClassWithInjectedProperties::class =>
                self::autowireDescriptor(FakeClassWithInjectedProperties::class),
        ]);

        $keyed = $plan->injectPropertyEdges['keyedProperty'] ?? null;
        $optional = $plan->injectPropertyEdges['optionalProperty'] ?? null;

        self::assertSame('key1', $keyed?->dependency?->key);
        self::assertFalse($keyed->soft);
        self::assertTrue($optional?->soft);
    }

    public function testCompile_WithStaticInjectMethod_RecordsInvalidInjectMemberAndOmitsMemberEdges(): void
    {
        $plan = self::compileSingle([
            FakeClassWithStaticInjectMethod::class =>
                self::autowireDescriptor(FakeClassWithStaticInjectMethod::class),
        ]);

        self::assertCount(1, $plan->invalidInjectMemberMessages);
        self::assertStringContainsString('setObj', $plan->invalidInjectMemberMessages[0] ?? '');
        self::assertSame([], $plan->injectMethodEdges);
        self::assertSame([], $plan->injectPropertyEdges);
    }

    public function testCompile_WithAbstractClass_RecordsNonInstantiable(): void
    {
        $plan = self::compileSingle([
            FakeAbstractClass::class => self::autowireDescriptor(FakeAbstractClass::class),
        ]);

        self::assertNotNull($plan->nonInstantiableMessage);
        self::assertStringContainsString(FakeAbstractClass::class, $plan->nonInstantiableMessage ?? '');
        self::assertSame([], $plan->argumentEdges);
    }

    public function testCompile_WithMutator_ProducesEdgesForParametersAfterTheInstance(): void
    {
        $plan = self::compileSingle([
            FakeClassNoConstructor::class => self::autowireDescriptor(
                FakeClassNoConstructor::class,
                static fn (FakeClassNoConstructor $instance, FakeInterfaceOne $extra) => $instance
            ),
        ]);

        self::assertCount(1, $plan->mutatorEdges);
        self::assertSame('extra', self::edgeAt($plan->mutatorEdges, 0)->name);
        self::assertSame(
            [[FakeInterfaceOne::class]],
            self::edgeAt($plan->mutatorEdges, 0)->dependency?->alternatives
        );
    }

    public function testCompile_WithClosureFactory_ProducesParameterEdgesAndDeclaredReturnType(): void
    {
        $plan = self::compileSingle([
            FakeInterfaceOne::class => self::closureDescriptor(
                FakeInterfaceOne::class,
                static fn (
                    FakeClassNoConstructor $dep,
                    ?FakeInterfaceTwo $opt
                ): FakeClassImplementsInterfaces => new FakeClassImplementsInterfaces()
            ),
        ]);

        self::assertSame(FakeInterfaceOne::class, $plan->className);
        self::assertSame(ResolutionPlanKind::Factory, $plan->kind);
        self::assertCount(2, $plan->argumentEdges);
        self::assertSame('dep', self::edgeAt($plan->argumentEdges, 0)->name);
        self::assertFalse(self::edgeAt($plan->argumentEdges, 0)->soft);
        self::assertTrue(self::edgeAt($plan->argumentEdges, 1)->soft);
        self::assertSame(FakeClassImplementsInterfaces::class, $plan->declaredFactoryReturnType);
    }

    public function testCompile_WithUndeclaredFactoryReturnType_LeavesReturnTypeNull(): void
    {
        $plan = self::compileSingle([
            FakeClassNoConstructor::class => self::closureDescriptor(
                FakeClassNoConstructor::class,
                static fn () => new FakeClassNoConstructor()
            ),
        ]);

        self::assertNull($plan->declaredFactoryReturnType);
    }

    public function testCompile_WithImplementation_ProducesImplementationPlan(): void
    {
        $plan = self::compileSingle([
            FakeInterfaceOne::class => self::implementationDescriptor(
                FakeInterfaceOne::class,
                FakeClassImplementsInterfaces::class
            ),
        ]);

        self::assertSame(ResolutionPlanKind::Implementation, $plan->kind);
        self::assertSame(FakeClassImplementsInterfaces::class, $plan->implementationTarget);
        self::assertSame([], $plan->argumentEdges);
    }

    public function testCompile_WithObjectInstance_ProducesEdgelessLeaf(): void
    {
        $plan = self::compileSingle([
            FakeClassNoConstructor::class => self::objectDescriptor(
                FakeClassNoConstructor::class,
                new FakeClassNoConstructor()
            ),
        ]);

        self::assertSame(ResolutionPlanKind::Leaf, $plan->kind);
        self::assertSame([], $plan->argumentEdges);
    }


    public function testCompile_WithSameClassUnderMultipleIds_ProducesEquivalentPlans(): void
    {
        $descriptor = self::autowireDescriptor(FakeClassWithDependencies::class);
        $plans = (new ResolutionPlanFactory())->compile([
            FakeClassWithDependencies::class => $descriptor,
            FakeClassWithDependencies::class . "\0key1" => $descriptor,
        ]);

        self::assertCount(2, $plans);

        foreach ($plans as $plan) {
            self::assertCount(2, $plan->argumentEdges);
        }
    }

}
