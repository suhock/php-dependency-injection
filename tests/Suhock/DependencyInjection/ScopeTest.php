<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Throwable;

/**
 * Test suite for {@see Scope} and scoped-lifetime resolution.
 */
final class ScopeTest extends AbstractDependencyInjectionTestCase
{
    private function createContainer(): Container
    {
        return Container::createDefault();
    }

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

            $exception = $exception instanceof DependencyInjectionException ?
                $exception->getConsolidatedException() ?? $exception->getPrevious() :
                $exception->getPrevious();
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
        $container = $this->createContainer();

        // Act
        $firstScope = $container->createScope();
        $secondScope = $container->createScope();

        // Assert
        self::assertNotSame($firstScope, $secondScope);
    }

    public function testContainer_ImplementsScopeFactoryInterface(): void
    {
        // Arrange & Act
        $container = $this->createContainer();

        // Assert
        self::assertInstanceOf(ScopeFactoryInterface::class, $container);
    }

    public function testGet_WithScopedService_ReturnsSameInstanceWithinScope(): void
    {
        // Arrange
        $container = $this->createContainer()->addScoped(FakeClassNoConstructor::class);
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
        $container = $this->createContainer()->addScoped(FakeClassNoConstructor::class);
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
        $container = $this->createContainer()->addScoped(FakeClassNoConstructor::class);

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn (Throwable $cause) => self::assertHasScopeExceptionCause($cause),
            $fn
        );
    }

    public function testGet_WithSingletonService_ReturnsSameInstanceFromRootAndScope(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingleton(FakeClassNoConstructor::class);
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
        $container = $this->createContainer()->addSingleton(FakeClassNoConstructor::class);

        // Act
        $firstInstance = $container->createScope()->get(FakeClassNoConstructor::class);
        $secondInstance = $container->createScope()->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($firstInstance, $secondInstance);
    }

    public function testGet_WithTransientDependingOnScoped_ResolvesDependencyFromScope(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addScoped(FakeClassNoConstructor::class)
            ->addTransient(FakeClassWithConstructor::class);
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
        $container = $this->createContainer()
            ->addScoped(FakeClassNoConstructor::class)
            ->addTransient(FakeClassWithConstructor::class);
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

    public function testGet_WithSingletonDependingOnScoped_ThrowsFromScope(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addScoped(FakeClassNoConstructor::class)
            ->addSingleton(FakeClassWithConstructor::class);
        $scope = $container->createScope();

        // Act
        $fn = static fn () => $scope->get(FakeClassWithConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassWithConstructor::class,
            static fn (Throwable $cause) => self::assertHasScopeExceptionCause($cause),
            $fn
        );
    }

    public function testGet_WithSingletonDependingOnScoped_ThrowsFromRoot(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addScoped(FakeClassNoConstructor::class)
            ->addSingleton(FakeClassWithConstructor::class);

        // Act
        $fn = static fn () => $container->get(FakeClassWithConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassWithConstructor::class,
            static fn (Throwable $cause) => self::assertHasScopeExceptionCause($cause),
            $fn
        );
    }

    public function testGet_WithSingletonFirstResolvedFromScope_CachesInRoot(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingleton(FakeClassNoConstructor::class);
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
        $container = $this->createContainer()->addKeyedScoped(FakeClassNoConstructor::class, 'key1');
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
        $container = $this->createContainer()->addKeyedScoped(FakeClassNoConstructor::class, 'key1');

        // Act
        $firstInstance = $container->createScope()->get(FakeClassNoConstructor::class, 'key1');
        $secondInstance = $container->createScope()->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertNotSame($firstInstance, $secondInstance);
    }

    public function testHas_WithScopedService_ReturnsTrueFromScope(): void
    {
        // Arrange
        $container = $this->createContainer()->addScoped(FakeClassNoConstructor::class);
        $scope = $container->createScope();

        // Act & Assert
        self::assertTrue($scope->has(FakeClassNoConstructor::class));
        self::assertFalse($scope->has(FakeClassWithConstructor::class));
    }

    public function testDispose_ThenGet_ThrowsScopeException(): void
    {
        // Arrange
        $container = $this->createContainer()->addScoped(FakeClassNoConstructor::class);
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
        $container = $this->createContainer()->addScoped(FakeClassNoConstructor::class);
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
        $container = $this->createContainer();
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
        $container = $this->createContainer()
            ->addScoped(FakeClassNoConstructor::class)
            ->addSingleton(FakeClassWithConstructor::class);
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
        // Arrange: scoped service depends on a singleton that depends back on the scoped service. The singleton's
        // dependency graph resolves in the root context, so the cycle must surface as a resolution failure — a
        // CircularDependencyException from the shared in-progress tracker, or a ScopeException from re-entering the
        // scoped service at the root — rather than recursing.
        $container = $this->createContainer()
            ->addScopedFactory(
                FakeClassNoConstructor::class,
                fn (FakeClassWithConstructor $dependency) => new FakeClassNoConstructor()
            )
            ->addSingleton(FakeClassWithConstructor::class);
        $scope = $container->createScope();

        // Act
        $fn = static fn () => $scope->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn (Throwable $cause) => self::assertHasCauseOfType(
                [ScopeException::class, CircularDependencyException::class],
                $cause
            ),
            $fn
        );
    }
}
