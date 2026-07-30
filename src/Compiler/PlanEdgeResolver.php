<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Compiler;

use Suhock\DependencyInjection\Descriptor;
use Suhock\DependencyInjection\DescriptorId;

use function sprintf;

/**
 * Resolves the dependency edges of a compiled {@see ResolutionPlan} against the frozen descriptor map, exactly as the
 * runtime would: per satisfied injection point, the first satisfiable alternative's first member present in the map.
 * Unsatisfiable and never-consulted injection points produce no edge.
 *
 * This is the single traversal of the resolved graph. {@see \Suhock\DependencyInjection\Validation\ContainerValidator}
 * and {@see DependencyGraphFactory} both consume it and project {@see ResolvedEdge} differently, so the graph the
 * validator checks and the graph the export publishes can never disagree on which edges exist.
 *
 * @internal
 */
final class PlanEdgeResolver
{
    /**
     * @param array<string, Descriptor<object>> $descriptors The full descriptor map the plans were compiled from,
     *     keyed by descriptor id
     */
    public function __construct(
        private readonly array $descriptors,
    ) {}

    /**
     * The out-edges the runtime would traverse when resolving a plan's service, plus the implementation target when
     * the plan has a resolvable one.
     *
     * @return list<ResolvedEdge>
     */
    public function resolve(ResolutionPlan $plan): array
    {
        $edges = [];

        if ($plan->implementationTarget !== null && isset($this->descriptors[$plan->implementationTarget])) {
            $edges[] = new ResolvedEdge($plan->implementationTarget, 'the implementation class', true, false);
        }

        foreach (self::describedEdges($plan) as [$injectionPoint, $edge]) {
            // A self edge does not resolve the service, it constructs it, so it is not a dependency on anything and
            // must not register as a self-loop. What the construction really depends on is already yielded above, as
            // the class's constructor edges.
            if ($edge->self) {
                continue;
            }

            $target = $this->chosenTarget($edge);

            if ($target !== null) {
                $edges[] = new ResolvedEdge($target, $injectionPoint, !$edge->soft, $edge->lazy);
            }
        }

        return $edges;
    }

    /**
     * Every edge of a plan paired with a description of its injection point, e.g.
     * <code>["parameter $x of __construct()", $edge]</code>. Unlike {@see resolve()}, this yields every injection
     * point, including the self edges and the unsatisfiable ones validation reports on.
     *
     * @return iterable<array{string, ResolutionPlanEdge}>
     */
    public static function describedEdges(ResolutionPlan $plan): iterable
    {
        $argumentLocation = $plan->kind === ResolutionPlanKind::Factory ? 'the factory' : '__construct()';

        foreach ($plan->argumentEdges as $edge) {
            yield [sprintf('parameter $%s of %s', $edge->name, $argumentLocation), $edge];
        }

        // A factory naming the service it produces constructs that service's class, so the class's constructor
        // arguments are edges of this service exactly as they are for an autowired class.
        foreach ($plan->selfConstructorEdges as $edge) {
            yield [sprintf('parameter $%s of __construct()', $edge->name), $edge];
        }
    }

    /**
     * The descriptor id the runtime would resolve for one injection point, or <code>null</code> when the container is
     * never consulted for it or no alternative is present in the descriptor map.
     */
    public function chosenTarget(ResolutionPlanEdge $edge): ?string
    {
        if ($edge->dependency === null) {
            return null;
        }

        foreach ($edge->dependency->alternatives as $alternative) {
            foreach ($alternative as $className) {
                $targetId = DescriptorId::compute($className, $edge->dependency->key);

                if (isset($this->descriptors[$targetId])) {
                    return $targetId;
                }
            }
        }

        return null;
    }

    public function isSatisfied(ResolutionPlanEdge $edge): bool
    {
        return $edge->dependency !== null && $this->chosenTarget($edge) !== null;
    }
}
