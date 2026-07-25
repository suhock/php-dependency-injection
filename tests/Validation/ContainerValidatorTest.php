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
use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Suhock\DependencyInjection\Builder\Descriptor;
use Suhock\DependencyInjection\Fakes\FakeAbstractClass;
use Suhock\DependencyInjection\Fakes\FakeClassImplementsInterfaces;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithDependencies;
use Suhock\DependencyInjection\Fakes\FakeClassWithDnfDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithIntersectionDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithKeyedDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithStringDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithUnionDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithVariadicConstructor;
use Suhock\DependencyInjection\Fakes\FakeCycleA;
use Suhock\DependencyInjection\Fakes\FakeCycleB;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\Fakes\FakeInterfaceTwo;
use Suhock\DependencyInjection\Fakes\FakeLazyBuiltinConsumer;
use Suhock\DependencyInjection\Fakes\FakeLazyConsumer;
use Suhock\DependencyInjection\Fakes\FakeLazyCounter;
use Suhock\DependencyInjection\Fakes\FakeLazyCycleA;
use Suhock\DependencyInjection\Fakes\FakeLazyCycleB;
use Suhock\DependencyInjection\Fakes\FakeLazyInterface;
use Suhock\DependencyInjection\Fakes\FakeLazyInterfaceConsumer;
use Suhock\DependencyInjection\Fakes\FakeLazyService;
use Suhock\DependencyInjection\Fakes\FakeLazyStatelessConsumer;
use Suhock\DependencyInjection\Fakes\FakeLazyStatelessService;
use Suhock\DependencyInjection\Fakes\FakeNullableCycleA;
use Suhock\DependencyInjection\Fakes\FakeNullableCycleB;
use Suhock\DependencyInjection\InstanceProvider\ClassInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\ClosureInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\ImplementationInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\Lifetime\ScopedStrategy;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;
use Suhock\DependencyInjection\Lifetime\TransientStrategy;
use Suhock\DependencyInjection\Resolver\ResolutionPlanFactory;
use Throwable;

/**
 * Test suite for {@see ContainerValidator}, covering the guaranteed-failure rules matrix: each kind has a failing
 * and a passing configuration, built from hand-assembled descriptor maps compiled by the real
 * {@see ResolutionPlanFactory}.
 */
final class ContainerValidatorTest extends TestCase
{
    /** @var array<string, Descriptor<object>> */
    private array $descriptors = [];

    #[Override]
    protected function setUp(): void
    {
        $this->descriptors = [];
    }

    /**
     * @param class-string $className
     */
    private function addClass(string $className, string $lifetime = 'transient', ?string $key = null): void
    {
        $this->add($className, new ClassInstanceProvider($className), $lifetime, $key);
    }

    /**
     * @param class-string $className
     */
    private function addFactory(
        string $className,
        Closure $factory,
        string $lifetime = 'transient',
        ?string $key = null,
    ): void {
        $this->add($className, new ClosureInstanceProvider($className, $factory), $lifetime, $key);
    }

    /**
     * @param class-string $className
     * @param class-string $implementationClassName
     */
    private function addImplementation(string $className, string $implementationClassName): void
    {
        $this->add(
            $className,
            new ImplementationInstanceProvider($className, $implementationClassName),
            'transient',
            null,
        );
    }

    /**
     * @param class-string $className
     * @param InstanceProviderInterface<object> $provider
     */
    private function add(
        string $className,
        InstanceProviderInterface $provider,
        string $lifetime,
        ?string $key,
    ): void {
        $strategy = match ($lifetime) {
            'singleton' => new SingletonStrategy($className),
            'scoped' => new ScopedStrategy($className),
            default => new TransientStrategy($className),
        };

        $id = $key === null ? $className : $className . "\0" . $key;
        $this->descriptors[$id] = new Descriptor($className, $strategy, $provider);
    }

    /**
     * @return list<ValidationIssue>
     */
    private function collectIssues(): array
    {
        $plans = (new ResolutionPlanFactory())->compile($this->descriptors);

        try {
            (new ContainerValidator($this->descriptors))->validate($plans);
        } catch (ContainerValidationException $exception) {
            return $exception->getIssues();
        }

        return [];
    }

    private function assertNoIssues(): void
    {
        self::assertSame([], $this->collectIssues());
    }

    private function assertSoleIssueKind(ValidationIssueKind $kind): ValidationIssue
    {
        $issues = $this->collectIssues();
        self::assertCount(1, $issues);
        $issue = $issues[0] ?? null;

        if ($issue === null) {
            self::fail('Expected exactly one validation issue');
        }

        self::assertSame($kind, $issue->kind);

        return $issue;
    }

    public function testValidate_WithMissingRequiredDependency_ReportsMissingDependency(): void
    {
        // Throwable is added; RuntimeException is not.
        $this->addClass(FakeClassWithDependencies::class);
        $this->addFactory(Throwable::class, static fn(): RuntimeException => new RuntimeException());

        $issue = $this->assertSoleIssueKind(ValidationIssueKind::MissingDependency);
        self::assertSame(FakeClassWithDependencies::class, $issue->className);
        self::assertStringContainsString('$runtimeException', $issue->message);
    }

    public function testValidate_WithAllDependenciesAdded_ReportsNothing(): void
    {
        $this->addClass(FakeClassWithDependencies::class);
        $this->addFactory(Throwable::class, static fn(): RuntimeException => new RuntimeException());
        $this->addFactory(RuntimeException::class, static fn(): RuntimeException => new RuntimeException());

        $this->assertNoIssues();
    }

    public function testValidate_WithSoftMissingDependency_ReportsNothing(): void
    {
        // The factory's parameter is nullable, so a missing dependency self-heals to null.
        $this->addFactory(
            FakeClassNoConstructor::class,
            static fn(?FakeInterfaceOne $missing): FakeClassNoConstructor => new FakeClassNoConstructor(),
        );

        $this->assertNoIssues();
    }

    public function testValidate_WithNoUnionMemberResolvable_ReportsMissingDependency(): void
    {
        $this->addClass(FakeClassWithUnionDependency::class);

        $issue = $this->assertSoleIssueKind(ValidationIssueKind::MissingDependency);
        self::assertStringContainsString(FakeInterfaceOne::class, $issue->message);
        self::assertStringContainsString(FakeInterfaceTwo::class, $issue->message);
    }

    public function testValidate_WithOneUnionMemberResolvable_ReportsNothing(): void
    {
        $this->addClass(FakeClassWithUnionDependency::class);
        $this->addImplementation(FakeInterfaceTwo::class, FakeClassImplementsInterfaces::class);
        $this->addClass(FakeClassImplementsInterfaces::class);

        $this->assertNoIssues();
    }

    public function testValidate_WithNoIntersectionMemberResolvable_ReportsMissingDependency(): void
    {
        $this->addClass(FakeClassWithIntersectionDependency::class);

        $this->assertSoleIssueKind(ValidationIssueKind::MissingDependency);
    }

    public function testValidate_WithSomeIntersectionMemberResolvable_ReportsNothing(): void
    {
        // Honest partial check: one member being resolvable is all that can be verified statically.
        $this->addClass(FakeClassWithIntersectionDependency::class);
        $this->addImplementation(FakeInterfaceOne::class, FakeClassImplementsInterfaces::class);
        $this->addClass(FakeClassImplementsInterfaces::class);

        $this->assertNoIssues();
    }

    public function testValidate_WithDnfDependencyMemberResolvable_ReportsNothing(): void
    {
        // Satisfies the (One&Two) conjunction of the DNF type via its One member.
        $this->addClass(FakeClassWithDnfDependency::class);
        $this->addImplementation(FakeInterfaceOne::class, FakeClassImplementsInterfaces::class);
        $this->addClass(FakeClassImplementsInterfaces::class);

        $this->assertNoIssues();
    }

    public function testValidate_WithRequiredVariadicElementMissing_ReportsMissingDependency(): void
    {
        $this->addFactory(
            FakeClassWithVariadicConstructor::class,
            static fn(FakeClassNoConstructor ...$items): FakeClassWithVariadicConstructor
                => new FakeClassWithVariadicConstructor(),
        );

        $this->assertSoleIssueKind(ValidationIssueKind::MissingDependency);
    }

    public function testValidate_WithRequiredBuiltinParameter_ReportsUnresolvableParameter(): void
    {
        $this->addClass(FakeClassWithStringDependency::class);

        $issue = $this->assertSoleIssueKind(ValidationIssueKind::UnresolvableParameter);
        self::assertStringContainsString('string', $issue->message);
    }

    public function testValidate_WithDefaultedBuiltinParameter_ReportsNothing(): void
    {
        $this->addFactory(
            FakeClassNoConstructor::class,
            static fn(string $name = 'default'): FakeClassNoConstructor => new FakeClassNoConstructor(),
        );

        $this->assertNoIssues();
    }

    public function testValidate_WithKeyedDependencyAbsent_ReportsMissingKeyedDependency(): void
    {
        $this->addClass(FakeClassWithKeyedDependency::class);

        $this->assertSoleIssueKind(ValidationIssueKind::MissingKeyedDependency);
    }

    public function testValidate_WithSameClassAddedUnkeyed_StillReportsMissingKeyedDependency(): void
    {
        // A keyed dependency never falls back to the unkeyed descriptor.
        $this->addClass(FakeClassWithKeyedDependency::class);
        $this->addClass(FakeClassNoConstructor::class);

        $this->assertSoleIssueKind(ValidationIssueKind::MissingKeyedDependency);
    }

    public function testValidate_WithKeyedDependencyAdded_ReportsNothing(): void
    {
        $this->addClass(FakeClassWithKeyedDependency::class);
        $this->addClass(FakeClassNoConstructor::class, 'transient', 'key1');

        $this->assertNoIssues();
    }

    public function testValidate_WithImplementationTargetMissing_ReportsMissingImplementation(): void
    {
        $this->addImplementation(FakeInterfaceOne::class, FakeClassImplementsInterfaces::class);

        $this->assertSoleIssueKind(ValidationIssueKind::MissingImplementation);
    }

    public function testValidate_WithImplementationTargetAdded_ReportsNothing(): void
    {
        $this->addImplementation(FakeInterfaceOne::class, FakeClassImplementsInterfaces::class);
        $this->addClass(FakeClassImplementsInterfaces::class);

        $this->assertNoIssues();
    }

    public function testValidate_WithUnrelatedFinalFactoryReturnType_ReportsFactoryReturnTypeMismatch(): void
    {
        // FakeClassNoConstructor is final and does not implement FakeInterfaceOne.
        $this->addFactory(
            FakeInterfaceOne::class,
            static fn(): FakeClassNoConstructor => new FakeClassNoConstructor(),
        );

        $this->assertSoleIssueKind(ValidationIssueKind::FactoryReturnTypeMismatch);
    }

    public function testValidate_WithCompatibleFactoryReturnType_ReportsNothing(): void
    {
        $this->addFactory(
            FakeInterfaceOne::class,
            static fn(): FakeClassImplementsInterfaces => new FakeClassImplementsInterfaces(),
        );

        $this->assertNoIssues();
    }

    public function testValidate_WithUndeclaredFactoryReturnType_ReportsNothing(): void
    {
        $this->addFactory(FakeInterfaceOne::class, static fn() => new FakeClassImplementsInterfaces());

        $this->assertNoIssues();
    }

    public function testValidate_WithAbstractClass_ReportsNonInstantiableClass(): void
    {
        $this->addClass(FakeAbstractClass::class);

        $this->assertSoleIssueKind(ValidationIssueKind::NonInstantiableClass);
    }

    public function testValidate_WithAllRequiredEdgeCycle_ReportsCircularDependencyOnce(): void
    {
        $this->addClass(FakeCycleA::class);
        $this->addClass(FakeCycleB::class);

        $issue = $this->assertSoleIssueKind(ValidationIssueKind::CircularDependency);
        self::assertStringContainsString(FakeCycleA::class, $issue->message);
        self::assertStringContainsString(FakeCycleB::class, $issue->message);
    }

    public function testValidate_WithSoftEdgeOnCycle_ReportsNothing(): void
    {
        // FakeNullableCycleB's reference back is nullable: the cycle self-heals at runtime.
        $this->addClass(FakeNullableCycleA::class);
        $this->addClass(FakeNullableCycleB::class);

        $this->assertNoIssues();
    }

    public function testValidate_WithSelfCycle_ReportsCircularDependency(): void
    {
        $this->addFactory(
            FakeInterfaceOne::class,
            static fn(FakeInterfaceOne $self): FakeClassImplementsInterfaces
                => new FakeClassImplementsInterfaces(),
        );

        $this->assertSoleIssueKind(ValidationIssueKind::CircularDependency);
    }

    public function testValidate_WithRequiredCaptivePath_ReportsCaptiveDependency(): void
    {
        // Singleton -> (required) transient -> (required) scoped: guaranteed ScopeException from the root context.
        $this->addFactory(
            FakeInterfaceOne::class,
            static fn(FakeInterfaceTwo $transient): FakeClassImplementsInterfaces
                => new FakeClassImplementsInterfaces(),
            'singleton',
        );
        $this->addFactory(
            FakeInterfaceTwo::class,
            static fn(FakeClassNoConstructor $scoped): FakeClassImplementsInterfaces
                => new FakeClassImplementsInterfaces(),
        );
        $this->addClass(FakeClassNoConstructor::class, 'scoped');

        $issue = $this->assertSoleIssueKind(ValidationIssueKind::CaptiveDependency);
        self::assertSame(FakeInterfaceOne::class, $issue->className);
        self::assertStringContainsString(FakeClassNoConstructor::class, $issue->message);
    }

    public function testValidate_WithSoftEdgeOnCaptivePath_ReportsNothing(): void
    {
        $this->addFactory(
            FakeInterfaceOne::class,
            static fn(FakeInterfaceTwo $transient): FakeClassImplementsInterfaces
                => new FakeClassImplementsInterfaces(),
            'singleton',
        );
        $this->addFactory(
            FakeInterfaceTwo::class,
            static fn(?FakeClassNoConstructor $scoped): FakeClassImplementsInterfaces
                => new FakeClassImplementsInterfaces(),
        );
        $this->addClass(FakeClassNoConstructor::class, 'scoped');

        $this->assertNoIssues();
    }

    public function testValidate_WithScopedRequiringScoped_ReportsNothing(): void
    {
        // Scoped-to-scoped is legal from a live scope; only a singleton root makes a scoped target captive.
        $this->addFactory(
            FakeInterfaceOne::class,
            static fn(FakeClassNoConstructor $other): FakeClassImplementsInterfaces
                => new FakeClassImplementsInterfaces(),
            'scoped',
        );
        $this->addClass(FakeClassNoConstructor::class, 'scoped');

        $this->assertNoIssues();
    }

    public function testValidate_WithMultipleDefects_AggregatesAllIssues(): void
    {
        $this->addClass(FakeClassWithDependencies::class);
        $this->addClass(FakeClassWithStringDependency::class);
        $this->addClass(FakeCycleA::class);
        $this->addClass(FakeCycleB::class);

        $issues = $this->collectIssues();

        $kinds = array_map(static fn(ValidationIssue $issue) => $issue->kind, $issues);
        self::assertContains(ValidationIssueKind::MissingDependency, $kinds);
        self::assertContains(ValidationIssueKind::UnresolvableParameter, $kinds);
        self::assertContains(ValidationIssueKind::CircularDependency, $kinds);
        self::assertCount(4, $issues);
    }

    public function testValidate_WithLazyDependencyBackedByAutowiredClass_ReportsNothing(): void
    {
        // The lazy dependency resolves to an autowired class, which the container can build as a ghost.
        $this->addClass(FakeLazyConsumer::class);
        $this->addClass(FakeLazyService::class);
        $this->addClass(FakeLazyCounter::class);

        $this->assertNoIssues();
    }

    public function testValidate_WithLazyDependencyBackedByConcreteFactory_ReportsNothing(): void
    {
        // The lazy dependency resolves to a factory with a concrete return type, which the container can proxy.
        $this->addClass(FakeLazyConsumer::class);
        $this->addFactory(
            FakeLazyService::class,
            static fn(): FakeLazyService => new FakeLazyService(new FakeLazyCounter()),
        );

        $this->assertNoIssues();
    }

    public function testValidate_WithLazyDependencyBackedByInterfaceFactory_ReportsUnbuildableLazyDependency(): void
    {
        // The factory declares an interface return type, so no concrete class is statically known to proxy.
        $this->addClass(FakeLazyInterfaceConsumer::class);
        $this->addFactory(
            FakeLazyInterface::class,
            static fn(): FakeLazyInterface => new FakeLazyService(new FakeLazyCounter()),
        );

        $issue = $this->assertSoleIssueKind(ValidationIssueKind::UnbuildableLazyDependency);
        self::assertSame(FakeLazyInterfaceConsumer::class, $issue->className);
    }

    public function testValidate_WithLazyBuiltinParameter_ReportsUnbuildableLazyDependency(): void
    {
        $this->addClass(FakeLazyBuiltinConsumer::class);

        $this->assertSoleIssueKind(ValidationIssueKind::UnbuildableLazyDependency);
    }

    public function testValidate_WithLazyDependencyOnPropertylessClass_ReportsUnbuildableLazyDependency(): void
    {
        // A property-less class has no state to defer, so PHP cannot build a lazy object for it.
        $this->addClass(FakeLazyStatelessConsumer::class);
        $this->addClass(FakeLazyStatelessService::class);

        $this->assertSoleIssueKind(ValidationIssueKind::UnbuildableLazyDependency);
    }

    public function testValidate_WithLazyEdgeOnDependencyCycle_ReportsNothing(): void
    {
        // The A -> B edge is lazy, so it does not construct B while A is built: the cycle is not a guaranteed failure.
        $this->addClass(FakeLazyCycleA::class);
        $this->addClass(FakeLazyCycleB::class);

        $this->assertNoIssues();
    }
}
