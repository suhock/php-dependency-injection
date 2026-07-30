<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Compiler;

use Suhock\DependencyInjection\AbstractDependencyInjectionTestCase;
use Suhock\DependencyInjection\ContainerBuilder;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\Descriptor;
use Suhock\DependencyInjection\DescriptorId;
use Suhock\DependencyInjection\Fakes\FakeCache;
use Suhock\DependencyInjection\Fakes\FakeClassImplementsInterfaces;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithDependencies;
use Suhock\DependencyInjection\Fakes\FakeClassWithKeyedDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithStringDependency;
use Suhock\DependencyInjection\Fakes\FakeClassWithUnionDependency;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;
use Suhock\DependencyInjection\Fakes\FakeInterfaceTwo;
use Suhock\DependencyInjection\Fakes\FakeLazyConsumer;
use Suhock\DependencyInjection\Fakes\FakeLazyService;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderFactory;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;
use Suhock\DependencyInjection\Lifetime\TransientStrategy;
use Suhock\DependencyInjection\ScopeFactoryInterface;
use Suhock\DependencyInjection\Validation\ContainerValidationException;
use Suhock\DependencyInjection\Validation\ValidationIssue;
use Suhock\DependencyInjection\Validation\ValidationIssueKind;

use function array_diff;
use function array_keys;
use function array_map;
use function array_values;
use function count;

/**
 * Test suite for {@see ContainerCompiler}, exercised directly on hand-assembled descriptor maps: the container's
 * self-bindings, the compile-validate-produce pipeline, graph reuse through the cache, and the graph export.
 */
final class ContainerCompilerTest extends AbstractDependencyInjectionTestCase
{
    public function testCompile_WithDescriptors_ProductResolvesTheService(): void
    {
        // Arrange
        $descriptors = self::unkeyed(self::singleton(FakeClassNoConstructor::class));

        // Act
        $container = ContainerCompiler::createDefault()->compile($descriptors);

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $container->get(FakeClassNoConstructor::class));
    }

    public function testCompile_WithAnyConfiguration_ProductHasTheAutoBindings(): void
    {
        // Arrange
        $descriptors = self::unkeyed(self::singleton(FakeClassNoConstructor::class));

        // Act
        $container = ContainerCompiler::createDefault()->compile($descriptors);

        // Assert
        self::assertSame($container, $container->get(ContainerInterface::class));
        self::assertSame($container, $container->get(ScopeFactoryInterface::class));
    }

    public function testCompile_WhenConfigurationSuppliesAnAutoBoundService_KeepsTheSuppliedDescriptor(): void
    {
        // Arrange: a container supplied as the instance for ContainerInterface, which auto-binding would otherwise
        // bind to the product itself.
        $suppliedContainer = ContainerBuilder::createDefault()->build();
        $descriptors = self::unkeyed(self::singleton(ContainerInterface::class, $suppliedContainer));

        // Act
        $container = ContainerCompiler::createDefault()->compile($descriptors);

        // Assert
        self::assertSame($suppliedContainer, $container->get(ContainerInterface::class));
        self::assertNotSame($container, $container->get(ContainerInterface::class));
    }

    public function testCompile_WithAnyConfiguration_LeavesTheCallersDescriptorMapUnchanged(): void
    {
        // Arrange: the compiler adds its self-bindings to its own copy, so the caller's configuration is untouched.
        $descriptors = self::unkeyed(self::singleton(FakeClassNoConstructor::class));

        // Act
        ContainerCompiler::createDefault()->compile($descriptors);

        // Assert
        self::assertSame([FakeClassNoConstructor::class], array_keys($descriptors));
    }

    public function testCompile_WithDefectiveConfiguration_ThrowsAggregatedValidationException(): void
    {
        // Arrange: two independent defects, a missing required dependency and an unresolvable builtin parameter.
        $descriptors = self::unkeyed(
            self::transient(FakeClassWithDependencies::class),
            self::transient(FakeClassWithStringDependency::class),
        );

        // Act
        try {
            ContainerCompiler::createDefault()->compile($descriptors);
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

    public function testCompile_CalledTwiceWithTheSameDescriptors_ProducesIndependentProducts(): void
    {
        // Arrange
        $compiler = ContainerCompiler::createDefault();
        $descriptors = self::unkeyed(self::singleton(FakeClassNoConstructor::class));

        // Act
        $first = $compiler->compile($descriptors);
        $second = $compiler->compile($descriptors);

        // Assert: each product caches its own singleton.
        self::assertNotSame($first, $second);
        self::assertNotSame(
            $first->get(FakeClassNoConstructor::class),
            $second->get(FakeClassNoConstructor::class),
        );
    }

    public function testCompile_WithCache_StoresTheCompiledGraphUnderTheConfigurationFingerprint(): void
    {
        // Arrange
        $cache = new FakeCache();
        $descriptors = self::unkeyed(self::singleton(FakeClassNoConstructor::class));

        // Act
        ContainerCompiler::createDefault($cache)->compile($descriptors);

        // Assert
        self::assertCount(1, $cache->idsWithPrefix('sdi:graph:'));
    }

    public function testCompile_WithIdenticalConfigurationAndWarmCache_ReusesTheStoredGraph(): void
    {
        // Arrange: two compilers, same configuration shape, same cache.
        $cache = new FakeCache();
        ContainerCompiler::createDefault($cache)
            ->compile(self::unkeyed(self::singleton(FakeClassNoConstructor::class)));
        $storesAfterFirstCompile = count($cache->storedIds);

        // Act
        $container = ContainerCompiler::createDefault($cache)
            ->compile(self::unkeyed(self::singleton(FakeClassNoConstructor::class)));

        // Assert: the second compilation stored nothing new and its product still resolves.
        self::assertCount($storesAfterFirstCompile, $cache->storedIds);
        self::assertInstanceOf(FakeClassNoConstructor::class, $container->get(FakeClassNoConstructor::class));
    }

    public function testCompile_WithChangedConfiguration_StoresASecondGraph(): void
    {
        // Arrange
        $cache = new FakeCache();
        ContainerCompiler::createDefault($cache)
            ->compile(self::unkeyed(self::singleton(FakeClassNoConstructor::class)));

        // Act
        ContainerCompiler::createDefault($cache)
            ->compile(self::unkeyed(self::transient(FakeClassNoConstructor::class)));

        // Assert
        self::assertCount(2, $cache->idsWithPrefix('sdi:graph:'));
    }

    public function testCompile_WithDefectiveConfigurationAndCache_StoresNoGraph(): void
    {
        // Arrange
        $cache = new FakeCache();
        $descriptors = self::unkeyed(self::transient(FakeClassWithStringDependency::class));

        // Act
        try {
            ContainerCompiler::createDefault($cache)->compile($descriptors);
            self::fail('Expected ' . ContainerValidationException::class);
        } catch (ContainerValidationException) {
        }

        // Assert
        self::assertSame([], $cache->idsWithPrefix('sdi:graph:'));
    }

    public function testCompile_WithUnfingerprintableConfiguration_CompilesWithoutStoringAGraph(): void
    {
        // Arrange: a factory from an internal function has no definition site to fingerprint. Its parameter is
        // optional and its return is unchecked, so the configuration still validates.
        $cache = new FakeCache();
        $descriptors = self::unkeyed(self::singleton(FakeClassNoConstructor::class, phpversion(...)));

        // Act
        $container = ContainerCompiler::createDefault($cache)->compile($descriptors);

        // Assert
        self::assertTrue($container->has(FakeClassNoConstructor::class));
        self::assertSame([], $cache->idsWithPrefix('sdi:graph:'));
    }

    public function testCompile_WhenTheCachedGraphIsNotAnArray_RecompilesAndStoresTheGraph(): void
    {
        // Arrange: a foreign value under the graph key, as a shared cache with a stale format would hold.
        $cache = self::warmCache();
        $cache->values[self::graphKey($cache)] = 'not a plan set';
        $storesBefore = count($cache->storedIds);

        // Act
        $container = ContainerCompiler::createDefault($cache)
            ->compile(self::unkeyed(self::singleton(FakeClassNoConstructor::class)));

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $container->get(FakeClassNoConstructor::class));
        self::assertCount($storesBefore + 1, $cache->storedIds);
    }

    public function testCompile_WhenTheCachedGraphHoldsForeignEntries_RecompilesAndStoresTheGraph(): void
    {
        // Arrange: an array of the right shape but the wrong element type.
        $cache = self::warmCache();
        $cache->values[self::graphKey($cache)] = [FakeClassNoConstructor::class => 'not a plan'];
        $storesBefore = count($cache->storedIds);

        // Act
        $container = ContainerCompiler::createDefault($cache)
            ->compile(self::unkeyed(self::singleton(FakeClassNoConstructor::class)));

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $container->get(FakeClassNoConstructor::class));
        self::assertCount($storesBefore + 1, $cache->storedIds);
    }

    public function testExportGraph_WithLinearChain_ExportsTheEdge(): void
    {
        // Arrange: FakeClassWithConstructor requires FakeClassNoConstructor via parameter $obj.
        $descriptors = self::unkeyed(
            self::singleton(FakeClassWithConstructor::class),
            self::singleton(FakeClassNoConstructor::class),
        );

        // Act
        $graph = ContainerCompiler::createDefault()->exportGraph($descriptors);

        // Assert
        self::assertCount(1, $graph->edges);
        $edge = $graph->edges[0] ?? null;
        self::assertSame(FakeClassWithConstructor::class, $edge?->sourceId);
        self::assertSame(FakeClassNoConstructor::class, $edge->targetId);
        self::assertTrue($edge->required);
        self::assertSame('parameter $obj of __construct()', $edge->injectionPoint);
    }

    public function testExportGraph_ServiceIds_IncludeUserServicesAndAutoBindings(): void
    {
        // Arrange
        $descriptors = self::unkeyed(self::singleton(FakeClassNoConstructor::class));

        // Act
        $graph = ContainerCompiler::createDefault()->exportGraph($descriptors);

        // Assert
        self::assertContains(FakeClassNoConstructor::class, $graph->serviceIds);
        self::assertContains(ContainerInterface::class, $graph->serviceIds);
        self::assertContains(ScopeFactoryInterface::class, $graph->serviceIds);
    }

    public function testExportGraph_WithKeyedDependency_RendersTheKeyedTargetId(): void
    {
        // Arrange: FakeClassWithKeyedDependency injects FakeClassNoConstructor under 'key1'.
        $descriptors = self::unkeyed(self::transient(FakeClassWithKeyedDependency::class));
        $descriptors[DescriptorId::compute(FakeClassNoConstructor::class, 'key1')]
            = self::singleton(FakeClassNoConstructor::class);

        // Act
        $graph = ContainerCompiler::createDefault()->exportGraph($descriptors);

        // Assert
        self::assertCount(1, $graph->edges);
        self::assertSame(FakeClassNoConstructor::class . '#key1', ($graph->edges[0] ?? null)?->targetId);
        self::assertContains(FakeClassNoConstructor::class . '#key1', $graph->serviceIds);
    }

    public function testExportGraph_WithImplementation_ExportsTheImplementationEdge(): void
    {
        // Arrange
        $descriptors = self::unkeyed(
            self::transient(FakeInterfaceOne::class, FakeClassImplementsInterfaces::class),
            self::transient(FakeClassImplementsInterfaces::class),
        );

        // Act
        $graph = ContainerCompiler::createDefault()->exportGraph($descriptors);

        // Assert
        self::assertCount(1, $graph->edges);
        $edge = $graph->edges[0] ?? null;
        self::assertSame(FakeInterfaceOne::class, $edge?->sourceId);
        self::assertSame(FakeClassImplementsInterfaces::class, $edge->targetId);
        self::assertSame('the implementation class', $edge->injectionPoint);
    }

    public function testExportGraph_WithSatisfiedSoftDependency_ExportsANonRequiredEdge(): void
    {
        // Arrange: the factory's nullable parameter is soft, but its dependency is added, so the edge exists.
        $descriptors = self::unkeyed(
            self::singleton(
                FakeClassWithConstructor::class,
                static fn(?FakeClassNoConstructor $obj): FakeClassWithConstructor
                        => new FakeClassWithConstructor($obj ?? new FakeClassNoConstructor()),
            ),
            self::singleton(FakeClassNoConstructor::class),
        );

        // Act
        $graph = ContainerCompiler::createDefault()->exportGraph($descriptors);

        // Assert
        self::assertCount(1, $graph->edges);
        self::assertFalse(($graph->edges[0] ?? null)?->required);
    }

    public function testExportGraph_WithUnionDependency_ExportsOnlyTheChosenEdge(): void
    {
        // Arrange: the union FakeInterfaceOne|FakeInterfaceTwo always chooses its first resolvable member.
        $descriptors = self::unkeyed(
            self::transient(FakeClassWithUnionDependency::class),
            self::transient(FakeInterfaceOne::class, FakeClassImplementsInterfaces::class),
            self::transient(FakeInterfaceTwo::class, FakeClassImplementsInterfaces::class),
            self::transient(FakeClassImplementsInterfaces::class),
        );

        // Act
        $graph = ContainerCompiler::createDefault()->exportGraph($descriptors);

        $unionTargets = [];

        foreach ($graph->edges as $edge) {
            if ($edge->sourceId === FakeClassWithUnionDependency::class) {
                $unionTargets[] = $edge->targetId;
            }
        }

        // Assert
        self::assertSame([FakeInterfaceOne::class], $unionTargets);
    }

    public function testExportGraph_WithDefectiveConfiguration_StillExportsWithoutTheBrokenEdge(): void
    {
        // Arrange: FakeClassWithDependencies is missing its required dependencies, so compile() would throw.
        $descriptors = self::unkeyed(self::transient(FakeClassWithDependencies::class));

        // Act
        $graph = ContainerCompiler::createDefault()->exportGraph($descriptors);

        // Assert
        self::assertContains(FakeClassWithDependencies::class, $graph->serviceIds);
        self::assertSame([], $graph->edges);
    }

    public function testExportGraph_RootsAreDerivable(): void
    {
        // Arrange: the roots (services nothing injects) are the ids that appear as no edge's target.
        $descriptors = self::unkeyed(
            self::singleton(FakeClassWithConstructor::class),
            self::singleton(FakeClassNoConstructor::class),
        );

        // Act
        $graph = ContainerCompiler::createDefault()->exportGraph($descriptors);
        $targets = array_map(static fn(DependencyGraphEdge $edge) => $edge->targetId, $graph->edges);
        $roots = array_values(array_diff($graph->serviceIds, $targets));

        // Assert: the auto-bindings surface as roots too; the user's root is the chain head.
        self::assertContains(FakeClassWithConstructor::class, $roots);
        self::assertNotContains(FakeClassNoConstructor::class, $roots);
    }

    public function testExportGraph_WithEveryEdgeKind_KeepsEveryEndpointInServiceIds(): void
    {
        // Arrange: an implementation edge, a union edge, a keyed edge, a lazy edge, and a plain required edge, plus a
        // service whose required dependencies are missing entirely.
        $descriptors = self::unkeyed(
            self::transient(FakeInterfaceOne::class, FakeClassImplementsInterfaces::class),
            self::transient(FakeInterfaceTwo::class, FakeClassImplementsInterfaces::class),
            self::transient(FakeClassImplementsInterfaces::class),
            self::transient(FakeClassWithUnionDependency::class),
            self::transient(FakeClassWithKeyedDependency::class),
            self::transient(FakeLazyConsumer::class),
            self::transient(FakeLazyService::class),
            self::singleton(FakeClassWithConstructor::class),
            self::singleton(FakeClassNoConstructor::class),
            self::transient(FakeClassWithDependencies::class),
        );
        $descriptors[DescriptorId::compute(FakeClassNoConstructor::class, 'key1')]
            = self::singleton(FakeClassNoConstructor::class);

        // Act
        $graph = ContainerCompiler::createDefault()->exportGraph($descriptors);

        // Assert: an edge naming an id absent from serviceIds would be unusable to any consumer of the export.
        self::assertNotSame([], $graph->edges);

        foreach ($graph->edges as $edge) {
            self::assertContains($edge->sourceId, $graph->serviceIds);
            self::assertContains($edge->targetId, $graph->serviceIds);
        }
    }

    public function testExportGraph_WithLazyDependency_ExportsARequiredEdge(): void
    {
        // Arrange: a #[Lazy] parameter defers construction, but resolution still fails without the dependency, so the
        // export reports it required. Validation's construction-order checks treat the same edge as non-required.
        $descriptors = self::unkeyed(
            self::transient(FakeLazyConsumer::class),
            self::transient(FakeLazyService::class),
        );

        // Act
        $graph = ContainerCompiler::createDefault()->exportGraph($descriptors);

        // Assert
        self::assertCount(1, $graph->edges);
        $edge = $graph->edges[0] ?? null;
        self::assertSame(FakeLazyService::class, $edge?->targetId);
        self::assertTrue($edge->required);
    }

    /**
     * A cache holding the stored graph of a single-singleton configuration, the arrangement for the tests that then
     * corrupt it.
     */
    private static function warmCache(): FakeCache
    {
        $cache = new FakeCache();
        ContainerCompiler::createDefault($cache)
            ->compile(self::unkeyed(self::singleton(FakeClassNoConstructor::class)));

        return $cache;
    }

    /**
     * The single graph key a warm cache holds.
     */
    private static function graphKey(FakeCache $cache): string
    {
        $ids = $cache->idsWithPrefix('sdi:graph:');
        self::assertCount(1, $ids);

        return $ids[0] ?? self::fail('The warm cache holds no graph.');
    }

    /**
     * @param Descriptor<object> ...$descriptors
     *
     * @return array<string, Descriptor<object>> The descriptors keyed by their unkeyed descriptor id
     */
    private static function unkeyed(Descriptor ...$descriptors): array
    {
        $map = [];

        foreach ($descriptors as $descriptor) {
            $map[DescriptorId::compute($descriptor->className, null)] = $descriptor;
        }

        return $map;
    }

    /**
     * @param class-string $className
     * @param class-string|callable|object|null $source
     *
     * @return Descriptor<object>
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    private static function singleton(string $className, string|callable|object|null $source = null): Descriptor
    {
        return new Descriptor(
            $className,
            new SingletonStrategy($className),
            InstanceProviderFactory::createInstanceProvider($className, $source),
        );
    }

    /**
     * @param class-string $className
     * @param class-string|callable|null $source
     *
     * @return Descriptor<object>
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    private static function transient(string $className, string|callable|null $source = null): Descriptor
    {
        return new Descriptor(
            $className,
            new TransientStrategy($className),
            InstanceProviderFactory::createInstanceProvider($className, $source),
        );
    }
}
