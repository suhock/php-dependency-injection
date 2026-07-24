<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use RuntimeException;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeDisposableClass;
use Suhock\DependencyInjection\Fakes\FakeDisposableClassWithDependency;
use Suhock\DependencyInjection\Fakes\FakeDisposalLog;
use Suhock\DependencyInjection\Validation\ContainerValidationException;
use Suhock\DependencyInjection\Validation\ValidationIssue;
use Suhock\DependencyInjection\Validation\ValidationIssueKind;
use Suhock\Disposable\DisposableInterface;
use Throwable;

use function array_map;
use function gc_collect_cycles;

/**
 * Test suite for {@see Scope} and scoped-lifetime resolution.
 */
final class ScopeTest extends AbstractDependencyInjectionTestCase
{
    /**
     * Asserts that an exception of one of the given classes appears somewhere in the given exception's cause chain,
     * following both regular previous links and consolidated exceptions (see
     * {@see DependencyInjectionException::__construct()}).
     *
     * @param non-empty-list<class-string<Throwable>> $expectedClasses
     */
    private static function assertHasCauseOfType(array $expectedClasses, ?Throwable $exception): void
    {
        while ($exception !== null) {
            foreach ($expectedClasses as $expectedClass) {
                if ($exception instanceof $expectedClass) {
                    return;
                }
            }

            $exception = $exception instanceof DependencyInjectionException
                ? $exception->getConsolidatedException() ?? $exception->getPrevious()
                : $exception->getPrevious();
        }

        self::fail('Failed asserting that the exception was caused by ' . implode(' or ', $expectedClasses));
    }

    /**
     * Asserts that a {@see ScopeException} appears somewhere in the given exception's cause chain.
     */
    private static function assertHasScopeExceptionCause(?Throwable $exception): void
    {
        self::assertHasCauseOfType([ScopeException::class], $exception);
    }

    public function testCreateScope_ReturnsNewScopeEachTime(): void
    {
        // Arrange
        $container = self::buildContainer(static fn(ContainerBuilder $builder) => null);

        // Act
        $firstScope = $container->createScope();
        $secondScope = $container->createScope();

        // Assert
        self::assertNotSame($firstScope, $secondScope);
    }

    public function testContainer_ImplementsScopeFactoryInterface(): void
    {
        // Arrange & Act
        $container = self::buildContainer(static fn(ContainerBuilder $builder) => null);

        // Assert
        self::assertInstanceOf(ScopeFactoryInterface::class, $container);
    }

    public function testGet_WithScopedService_ReturnsSameInstanceWithinScope(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeClassNoConstructor::class),
        );
        $scope = $container->createScope();

        // Act
        $firstInstance = $scope->get(FakeClassNoConstructor::class);
        $secondInstance = $scope->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($firstInstance, $secondInstance);
    }

    public function testGet_WithScopedService_ReturnsDistinctInstancesAcrossScopes(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeClassNoConstructor::class),
        );
        $firstScope = $container->createScope();
        $secondScope = $container->createScope();

        // Act
        $firstInstance = $firstScope->get(FakeClassNoConstructor::class);
        $secondInstance = $secondScope->get(FakeClassNoConstructor::class);

        // Assert
        self::assertNotSame($firstInstance, $secondInstance);
    }

    public function testGet_WithScopedServiceFromRoot_ThrowsScopeException(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeClassNoConstructor::class),
        );

        // Act
        $fn = static fn() => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn(Throwable $cause) => self::assertHasScopeExceptionCause($cause),
            $fn,
        );
    }

    public function testGet_WithSingletonService_ReturnsSameInstanceFromRootAndScope(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeClassNoConstructor::class),
        );
        $scope = $container->createScope();

        // Act
        $scopeInstance = $scope->get(FakeClassNoConstructor::class);
        $rootInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($scopeInstance, $rootInstance);
    }

    public function testGet_WithSingletonService_ReturnsSameInstanceAcrossScopes(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeClassNoConstructor::class),
        );

        // Act
        $firstInstance = $container->createScope()->get(FakeClassNoConstructor::class);
        $secondInstance = $container->createScope()->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($firstInstance, $secondInstance);
    }

    public function testGet_WithTransientDependingOnScoped_ResolvesDependencyFromScope(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeClassNoConstructor::class)
                ->addTransient(FakeClassWithConstructor::class),
        );
        $scope = $container->createScope();

        // Act
        $transientInstance = $scope->get(FakeClassWithConstructor::class);
        $scopedInstance = $scope->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($scopedInstance, $transientInstance->obj);
    }

    public function testGet_WithTransientDependingOnScoped_ResolvesDependencyFromEachScope(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeClassNoConstructor::class)
                ->addTransient(FakeClassWithConstructor::class),
        );
        $firstScope = $container->createScope();
        $secondScope = $container->createScope();

        // Act
        $firstInstance = $firstScope->get(FakeClassWithConstructor::class);
        $secondInstance = $secondScope->get(FakeClassWithConstructor::class);

        // Assert
        self::assertNotSame($firstInstance->obj, $secondInstance->obj);
        self::assertSame($firstScope->get(FakeClassNoConstructor::class), $firstInstance->obj);
        self::assertSame($secondScope->get(FakeClassNoConstructor::class), $secondInstance->obj);
    }

    public function testBuild_WithSingletonDependingOnScoped_ThrowsCaptiveDependencyValidationException(): void
    {
        // Arrange: FakeClassWithConstructor requires FakeClassNoConstructor via its constructor, so wiring the
        // dependency as scoped and the dependent as singleton always resolves the scoped service outside of a scope.
        // This used to surface as a ScopeException lazily from either the scope or the root container's get(); it is
        // now a build-time captive-dependency defect, caught before either can ever be called.
        $builder = self::createBuilder()->addScoped(FakeClassNoConstructor::class)
            ->addSingleton(FakeClassWithConstructor::class);

        // Act
        try {
            $builder->build();
            self::fail('Expected ' . ContainerValidationException::class);
        } catch (ContainerValidationException $exception) {
            // Assert
            $kinds = array_map(
                static fn(ValidationIssue $issue) => $issue->kind,
                $exception->getIssues(),
            );
            self::assertContains(ValidationIssueKind::CaptiveDependency, $kinds);
        }
    }

    public function testGet_WithSingletonFirstResolvedFromScope_CachesInRoot(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeClassNoConstructor::class),
        );
        $scope = $container->createScope();
        $scopeInstance = $scope->get(FakeClassNoConstructor::class);

        // Act
        $scope->dispose();
        $rootInstance = $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($scopeInstance, $rootInstance);
    }

    public function testGet_WithKeyedScopedService_ReturnsSameInstanceWithinScope(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addKeyedScoped(FakeClassNoConstructor::class, 'key1'),
        );
        $scope = $container->createScope();

        // Act
        $firstInstance = $scope->get(FakeClassNoConstructor::class, 'key1');
        $secondInstance = $scope->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame($firstInstance, $secondInstance);
    }

    public function testGet_WithKeyedScopedService_ReturnsDistinctInstancesAcrossScopes(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addKeyedScoped(FakeClassNoConstructor::class, 'key1'),
        );

        // Act
        $firstInstance = $container->createScope()->get(FakeClassNoConstructor::class, 'key1');
        $secondInstance = $container->createScope()->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertNotSame($firstInstance, $secondInstance);
    }

    public function testHas_WithScopedService_ReturnsTrueFromScope(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeClassNoConstructor::class),
        );
        $scope = $container->createScope();

        // Act & Assert
        self::assertTrue($scope->has(FakeClassNoConstructor::class));
        self::assertFalse($scope->has(FakeClassWithConstructor::class));
    }

    public function testDispose_ThenGet_ThrowsScopeException(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeClassNoConstructor::class),
        );
        $scope = $container->createScope();

        // Act
        $scope->dispose();

        // Assert
        $this->expectException(ScopeException::class);
        $scope->get(FakeClassNoConstructor::class);
    }

    public function testDispose_ThenHas_ThrowsScopeException(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeClassNoConstructor::class),
        );
        $scope = $container->createScope();

        // Act
        $scope->dispose();

        // Assert
        $this->expectException(ScopeException::class);
        $scope->has(FakeClassNoConstructor::class);
    }

    public function testDispose_CalledTwice_HasNoEffect(): void
    {
        // Arrange
        $container = self::buildContainer(static fn(ContainerBuilder $builder) => null);
        $scope = $container->createScope();

        // Act
        $scope->dispose();
        $scope->dispose();

        // Assert
        $this->expectException(ScopeException::class);
        $scope->get(FakeClassNoConstructor::class);
    }

    public function testDispose_DoesNotAffectOtherScopesOrRoot(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeClassNoConstructor::class),
        );
        $disposedScope = $container->createScope();
        $liveScope = $container->createScope();
        $liveInstance = $liveScope->get(FakeClassNoConstructor::class);

        // Act
        $disposedScope->dispose();

        // Assert
        self::assertSame($liveInstance, $liveScope->get(FakeClassNoConstructor::class));
    }

    public function testGet_WithCircularScopedAndSingletonServices_DoesNotRecurseInfinitely(): void
    {
        // Arrange: scoped service depends on a singleton that depends back on the scoped service. Build-time
        // validation now rejects a singleton with a *visible* required dependency on a scoped service (captive
        // dependency, see testBuild_WithSingletonDependingOnScoped_ThrowsCaptiveDependencyValidationException()), so
        // the singleton's dependency hides in its factory body, the same technique ContainerTest uses to keep an
        // edge invisible to validation and preserve a runtime-only backstop test. The
        // singleton's dependency graph still resolves in the root context, so the cycle must surface as a resolution
        // failure (a CircularDependencyException from the shared in-progress tracker, or a ScopeException from
        // re-entering the scoped service at the root) rather than recursing.
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScopedFactory(
                FakeClassNoConstructor::class,
                fn(FakeClassWithConstructor $dependency) => new FakeClassNoConstructor(),
            )
                ->addSingletonFactory(
                    FakeClassWithConstructor::class,
                    static fn(ContainerInterface $c): FakeClassWithConstructor
                        => new FakeClassWithConstructor($c->get(FakeClassNoConstructor::class)),
                ),
        );
        $scope = $container->createScope();

        // Act
        $fn = static fn() => $scope->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn(Throwable $cause) => self::assertHasCauseOfType(
                [ScopeException::class, CircularDependencyException::class],
                $cause,
            ),
            $fn,
        );
    }

    public function testDispose_WithScopedDisposableService_DisposesInstance(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeDisposableClass::class),
        );
        $scope = $container->createScope();
        $instance = $scope->get(FakeDisposableClass::class);

        // Act
        $scope->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }

    public function testDispose_WithDisposableDependencyGraph_DisposesDependentsBeforeDependencies(): void
    {
        // Arrange
        $log = new FakeDisposalLog();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingletonInstance(FakeDisposalLog::class, $log)
                ->addScoped(FakeDisposableClass::class)
                ->addScoped(FakeDisposableClassWithDependency::class),
        );
        $scope = $container->createScope();
        $scope->get(FakeDisposableClassWithDependency::class);

        // Act
        $scope->dispose();

        // Assert
        self::assertSame(['dependent', 'dependency'], $log->entries);
    }

    public function testDispose_WithTransientDisposableStillReferenced_DisposesInstance(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addTransient(FakeDisposableClass::class),
        );
        $scope = $container->createScope();
        $instance = $scope->get(FakeDisposableClass::class);

        // Act
        $scope->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }

    public function testDispose_WithTransientDisposableNoLongerReferenced_DoesNotDisposeInstance(): void
    {
        // Arrange
        $log = new FakeDisposalLog();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingletonInstance(FakeDisposalLog::class, $log)
                ->addTransient(FakeDisposableClass::class),
        );
        $scope = $container->createScope();
        $instance = $scope->get(FakeDisposableClass::class);

        // Act
        unset($instance);
        gc_collect_cycles();
        $scope->dispose();

        // Assert
        self::assertSame([], $log->entries);
    }

    public function testDispose_WithSingletonDisposableResolvedFromScope_DoesNotDisposeInstance(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeDisposableClass::class),
        );
        $scope = $container->createScope();
        $instance = $scope->get(FakeDisposableClass::class);

        // Act
        $scope->dispose();

        // Assert
        self::assertSame(0, $instance->disposeCount);
    }

    public function testDispose_WithShouldDisposeFalseScopedService_DoesNotDisposeInstance(): void
    {
        // Arrange
        $container = self::buildRawContainer([
            FakeDisposableClass::class => self::classDescriptor(
                FakeDisposableClass::class,
                'scoped',
                shouldDispose: false,
            ),
        ]);
        $scope = $container->createScope();
        $instance = $scope->get(FakeDisposableClass::class);

        // Act
        $scope->dispose();

        // Assert
        self::assertSame(0, $instance->disposeCount);
    }

    public function testDispose_CalledTwice_DisposesScopedInstanceOnlyOnce(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeDisposableClass::class),
        );
        $scope = $container->createScope();
        $instance = $scope->get(FakeDisposableClass::class);

        // Act
        $scope->dispose();
        $scope->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }

    public function testDispose_WhenDisposeThrows_MarksScopeDisposedAndRethrows(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScopedFactory(
                DisposableInterface::class,
                static fn() => new class implements DisposableInterface {
                    public function dispose(): void
                    {
                        throw new RuntimeException('dispose failed');
                    }
                },
            ),
        );
        $scope = $container->createScope();
        $scope->get(DisposableInterface::class);

        // Act
        $exception = null;

        try {
            $scope->dispose();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        // Assert
        self::assertInstanceOf(RuntimeException::class, $exception);
        $this->expectException(ScopeException::class);
        $scope->get(DisposableInterface::class);
    }
}
