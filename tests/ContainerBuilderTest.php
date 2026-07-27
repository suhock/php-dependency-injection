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
use Suhock\DependencyInjection\Fakes\FakeCache;
use Suhock\DependencyInjection\Fakes\FakeClassImplementsInterfaces;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithDependencies;
use Suhock\DependencyInjection\Fakes\FakeClassWithKeyedDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithStringDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithUnionDependency;
use Suhock\DependencyInjection\Fakes\FakeConfigurator;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\Fakes\FakeInterfaceTwo;
use Suhock\DependencyInjection\Fakes\FakeUnitEnum;
use Suhock\DependencyInjection\Validation\ContainerValidationException;
use Suhock\DependencyInjection\Validation\DependencyGraphEdge;
use Suhock\DependencyInjection\Validation\ValidationIssue;
use Suhock\DependencyInjection\Validation\ValidationIssueKind;
use Throwable;

use function array_map;

/**
 * Test suite for {@see ContainerBuilder}: the mutable configuration surface, duplicate detection, pre-build removal,
 * and the compile-validate-produce pipeline of {@see ContainerBuilder::build()}.
 */
final class ContainerBuilderTest extends AbstractDependencyInjectionTestCase
{
    public function testAdd_WithDuplicateClass_ThrowsContainerException(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class);

        // Act & Assert
        $this->expectException(ContainerException::class);
        $builder->addSingleton(FakeClassNoConstructor::class);
    }

    public function testAddKeyed_WithValidClass_ProductHasKeyedService(): void
    {
        // Arrange & Act
        $container = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')
            ->build();

        // Assert
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key1'));
        self::assertFalse($container->has(FakeClassNoConstructor::class));
    }

    public function testAddKeyed_WithDuplicateKey_ThrowsContainerException(): void
    {
        // Arrange
        $builder = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

        // Act & Assert
        $this->expectException(ContainerException::class);
        $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');
    }

    public function testAddKeyed_WhenUnkeyedServiceExists_AddsIndependentKeyedDescriptor(): void
    {
        // Arrange & Act
        $container = self::createBuilder()->addSingleton(FakeClassNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')
            ->build();

        // Assert
        self::assertNotSame(
            $container->get(FakeClassNoConstructor::class),
            $container->get(FakeClassNoConstructor::class, 'key1'),
        );
    }

    public function testConfigure_WithCallback_InvokesCallbackWithSelf(): void
    {
        // Arrange
        $builder = self::createBuilder();
        $configurator = $this->createMock(FakeConfigurator::class);
        $configurator->expects($this->once())
            ->method('configure')
            ->with($builder);

        // Act
        $builder->configure($configurator->configure(...));
    }

    public function testRemove_BeforeBuild_ProductLacksService(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class);

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class)->build();

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class));
    }

    public function testRemove_WithKey_RemovesOnlyKeyedService(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key2');

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class, 'key1')->build();

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class, 'key1'));
        self::assertTrue($container->has(FakeClassNoConstructor::class));
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key2'));
    }

    public function testRemove_WithoutKey_DoesNotRemoveKeyedService(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class)->build();

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class));
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key1'));
    }

    public function testRemove_WithEnumKey_RemovesKeyedService(): void
    {
        // Arrange
        $builder = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, FakeUnitEnum::Test);

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class, FakeUnitEnum::Test)->build();

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class, FakeUnitEnum::Test));
    }

    public function testRemove_ThenReAddUnderSameKey_ProductResolvesTheReplacement(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $builder = self::createBuilder()->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

        // Act
        $container = $builder->remove(FakeClassNoConstructor::class, 'key1')
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance)
            ->build();

        // Assert
        self::assertSame($expectedInstance, $container->get(FakeClassNoConstructor::class, 'key1'));
    }

    public function testBuild_WithDefectiveConfiguration_ThrowsAggregatedValidationException(): void
    {
        // Arrange: two independent defects, a missing required dependency and an unresolvable builtin parameter.
        $builder = self::createBuilder()->addTransient(FakeClassWithDependencies::class)
            ->addTransient(FakeClassWithStringDependency::class);

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
            self::assertContains(ValidationIssueKind::MissingDependency, $kinds);
            self::assertContains(ValidationIssueKind::UnresolvableParameter, $kinds);
        }
    }

    public function testBuild_AfterFixingAFailedBuild_Succeeds(): void
    {
        // Arrange: FakeClassWithDependencies requires Throwable and RuntimeException.
        $builder = self::createBuilder()->addTransient(FakeClassWithDependencies::class);

        try {
            $builder->build();
            self::fail('Expected ' . ContainerValidationException::class);
        } catch (ContainerValidationException) {
            // The builder must remain fully usable after the failed build.
        }

        // Act
        $container = $builder->addSingleton(Throwable::class, static fn(): RuntimeException => new RuntimeException())
            ->addSingleton(RuntimeException::class, static fn(): RuntimeException => new RuntimeException())
            ->build();

        // Assert
        self::assertInstanceOf(
            FakeClassWithDependencies::class,
            $container->get(FakeClassWithDependencies::class),
        );
    }

    public function testBuild_CalledTwice_ProducesIndependentProducts(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class);

        // Act
        $first = $builder->build();
        $second = $builder->build();

        // Assert: each product caches its own singleton.
        self::assertNotSame($first, $second);
        self::assertNotSame(
            $first->get(FakeClassNoConstructor::class),
            $second->get(FakeClassNoConstructor::class),
        );
    }

    public function testBuild_AfterBuild_AddedServicesDoNotAffectEarlierProducts(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class);

        // Act
        $first = $builder->build();
        $second = $builder->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')->build();

        // Assert
        self::assertFalse($first->has(FakeClassNoConstructor::class, 'key1'));
        self::assertTrue($second->has(FakeClassNoConstructor::class, 'key1'));
    }

    public function testBuild_WithCache_StoresTheCompiledGraphUnderTheConfigurationFingerprint(): void
    {
        // Arrange
        $cache = new FakeCache();

        // Act
        ContainerBuilder::createDefault($cache)->addSingleton(FakeClassNoConstructor::class)->build();

        // Assert
        self::assertCount(1, $cache->idsWithPrefix('sdi:graph:'));
    }

    public function testBuild_WithIdenticalConfigurationAndWarmCache_ReusesTheStoredGraph(): void
    {
        // Arrange: two builders, same configuration shape, same cache.
        $cache = new FakeCache();
        ContainerBuilder::createDefault($cache)->addSingleton(FakeClassNoConstructor::class)->build();
        $storesAfterFirstBuild = count($cache->storedIds);

        // Act
        $container = ContainerBuilder::createDefault($cache)->addSingleton(FakeClassNoConstructor::class)
            ->build();

        // Assert: the second build stored nothing new and its product still resolves.
        self::assertCount($storesAfterFirstBuild, $cache->storedIds);
        self::assertInstanceOf(FakeClassNoConstructor::class, $container->get(FakeClassNoConstructor::class));
    }

    public function testBuild_WithChangedConfiguration_StoresASecondGraph(): void
    {
        // Arrange
        $cache = new FakeCache();
        ContainerBuilder::createDefault($cache)->addSingleton(FakeClassNoConstructor::class)->build();

        // Act
        ContainerBuilder::createDefault($cache)->addTransient(FakeClassNoConstructor::class)->build();

        // Assert
        self::assertCount(2, $cache->idsWithPrefix('sdi:graph:'));
    }

    public function testBuild_WithDefectiveConfigurationAndCache_StoresNoGraph(): void
    {
        // Arrange
        $cache = new FakeCache();
        $builder = ContainerBuilder::createDefault($cache)->addTransient(FakeClassWithStringDependency::class);

        // Act
        try {
            $builder->build();
            self::fail('Expected ' . ContainerValidationException::class);
        } catch (ContainerValidationException) {
        }

        // Assert
        self::assertSame([], $cache->idsWithPrefix('sdi:graph:'));
    }

    public function testBuild_WithUnfingerprintableConfiguration_BuildsWithoutStoringAGraph(): void
    {
        // Arrange: a factory from an internal function has no definition site to fingerprint. Its parameter is
        // optional and its return is unchecked, so the configuration still validates.
        $cache = new FakeCache();

        // Act
        // @phpstan-ignore suhock.factoryReturnType (an internal function has no definition site to fingerprint)
        $container = ContainerBuilder::createDefault($cache)->addSingleton(FakeClassNoConstructor::class, phpversion(...))
            ->build();

        // Assert
        self::assertTrue($container->has(FakeClassNoConstructor::class));
        self::assertSame([], $cache->idsWithPrefix('sdi:graph:'));
    }

    public function testExportDependencyGraph_WithLinearChain_ExportsTheEdge(): void
    {
        // Arrange: FakeClassWithConstructor requires FakeClassNoConstructor via parameter $obj.
        $builder = self::createBuilder()->addSingleton(FakeClassWithConstructor::class)
            ->addSingleton(FakeClassNoConstructor::class);

        // Act
        $graph = $builder->exportDependencyGraph();

        // Assert
        self::assertCount(1, $graph->edges);
        $edge = $graph->edges[0] ?? null;
        self::assertSame(FakeClassWithConstructor::class, $edge?->sourceId);
        self::assertSame(FakeClassNoConstructor::class, $edge->targetId);
        self::assertTrue($edge->required);
        self::assertSame('parameter $obj of __construct()', $edge->injectionPoint);
    }

    public function testExportDependencyGraph_ServiceIds_IncludeUserServicesAndAutoBindings(): void
    {
        // Arrange
        $builder = self::createBuilder()->addSingleton(FakeClassNoConstructor::class);

        // Act
        $graph = $builder->exportDependencyGraph();

        // Assert
        self::assertContains(FakeClassNoConstructor::class, $graph->serviceIds);
        self::assertContains(ContainerInterface::class, $graph->serviceIds);
        self::assertContains(ScopeFactoryInterface::class, $graph->serviceIds);
    }

    public function testExportDependencyGraph_WithKeyedDependency_RendersTheKeyedTargetId(): void
    {
        // Arrange: FakeClassWithKeyedDependency injects FakeClassNoConstructor under 'key1'.
        $builder = self::createBuilder()->addTransient(FakeClassWithKeyedDependency::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

        // Act
        $graph = $builder->exportDependencyGraph();

        // Assert
        self::assertCount(1, $graph->edges);
        self::assertSame(FakeClassNoConstructor::class . '#key1', ($graph->edges[0] ?? null)?->targetId);
        self::assertContains(FakeClassNoConstructor::class . '#key1', $graph->serviceIds);
    }

    public function testExportDependencyGraph_WithImplementation_ExportsTheImplementationEdge(): void
    {
        // Arrange
        $builder = self::createBuilder()->addTransient(FakeInterfaceOne::class, FakeClassImplementsInterfaces::class)
            ->addTransient(FakeClassImplementsInterfaces::class);

        // Act
        $graph = $builder->exportDependencyGraph();

        // Assert
        self::assertCount(1, $graph->edges);
        $edge = $graph->edges[0] ?? null;
        self::assertSame(FakeInterfaceOne::class, $edge?->sourceId);
        self::assertSame(FakeClassImplementsInterfaces::class, $edge->targetId);
        self::assertSame('the implementation class', $edge->injectionPoint);
    }

    public function testExportDependencyGraph_WithSatisfiedSoftDependency_ExportsANonRequiredEdge(): void
    {
        // Arrange: the factory's nullable parameter is soft, but its dependency is added, so the edge exists.
        $builder = self::createBuilder()->addSingleton(
            FakeClassWithConstructor::class,
            static fn(?FakeClassNoConstructor $obj): FakeClassWithConstructor
                    => new FakeClassWithConstructor($obj ?? new FakeClassNoConstructor()),
        )
            ->addSingleton(FakeClassNoConstructor::class);

        // Act
        $graph = $builder->exportDependencyGraph();

        // Assert
        self::assertCount(1, $graph->edges);
        self::assertFalse(($graph->edges[0] ?? null)?->required);
    }

    public function testExportDependencyGraph_WithUnionDependency_ExportsOnlyTheChosenEdge(): void
    {
        // Arrange: the union FakeInterfaceOne|FakeInterfaceTwo always chooses its first resolvable member.
        $builder = self::createBuilder()->addTransient(FakeClassWithUnionDependency::class)
            ->addTransient(FakeInterfaceOne::class, FakeClassImplementsInterfaces::class)
            ->addTransient(FakeInterfaceTwo::class, FakeClassImplementsInterfaces::class)
            ->addTransient(FakeClassImplementsInterfaces::class);

        // Act
        $graph = $builder->exportDependencyGraph();

        $unionTargets = [];

        foreach ($graph->edges as $edge) {
            if ($edge->sourceId === FakeClassWithUnionDependency::class) {
                $unionTargets[] = $edge->targetId;
            }
        }

        // Assert
        self::assertSame([FakeInterfaceOne::class], $unionTargets);
    }

    public function testExportDependencyGraph_WithDefectiveConfiguration_StillExportsWithoutTheBrokenEdge(): void
    {
        // Arrange: FakeClassWithDependencies is missing its required dependencies, so build() would throw.
        $builder = self::createBuilder()->addTransient(FakeClassWithDependencies::class);

        // Act
        $graph = $builder->exportDependencyGraph();

        // Assert
        self::assertContains(FakeClassWithDependencies::class, $graph->serviceIds);
        self::assertSame([], $graph->edges);
    }

    public function testExportDependencyGraph_RootsAreDerivable(): void
    {
        // Arrange: the roots (services nothing injects) are the ids that appear as no edge's target.
        $builder = self::createBuilder()->addSingleton(FakeClassWithConstructor::class)
            ->addSingleton(FakeClassNoConstructor::class);

        // Act
        $graph = $builder->exportDependencyGraph();
        $targets = array_map(static fn(DependencyGraphEdge $edge) => $edge->targetId, $graph->edges);
        $roots = array_values(array_diff($graph->serviceIds, $targets));

        // Assert: the auto-bindings surface as roots too; the user's root is the chain head.
        self::assertContains(FakeClassWithConstructor::class, $roots);
        self::assertNotContains(FakeClassNoConstructor::class, $roots);
    }
}
