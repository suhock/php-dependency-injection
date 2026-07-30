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

use function array_keys;

/**
 * Projects a set of compiled {@see ResolutionPlan}s into the {@see DependencyGraph} published to external tooling:
 * every service, and every satisfied, chosen edge with the injection point it flows through.
 *
 * Purely informational, and deliberately not a validation step: a defective configuration still exports, so the
 * unsatisfiable injection points {@see \Suhock\DependencyInjection\Validation\ContainerValidator} reports simply
 * produce no edge here.
 *
 * @internal
 */
final class DependencyGraphFactory
{
    /**
     * @param array<string, Descriptor<object>> $descriptors The full descriptor map the plans were compiled from,
     *     keyed by descriptor id
     * @param PlanEdgeResolver $edgeResolver Resolves each plan's edges against $descriptors
     */
    public function __construct(
        private readonly array $descriptors,
        private readonly PlanEdgeResolver $edgeResolver,
    ) {}

    /**
     * Creates a factory with the default configuration.
     *
     * @param array<string, Descriptor<object>> $descriptors The full descriptor map the plans were compiled from,
     *     keyed by descriptor id
     */
    public static function createDefault(array $descriptors): self
    {
        return new self($descriptors, new PlanEdgeResolver($descriptors));
    }

    /**
     * @param array<string, ResolutionPlan> $plans The compiled plans, keyed by descriptor id
     */
    public function create(array $plans): DependencyGraph
    {
        $serviceIds = [];

        foreach (array_keys($this->descriptors) as $id) {
            $serviceIds[] = DescriptorId::display($id);
        }

        $edges = [];

        foreach ($plans as $id => $plan) {
            // A plan for a service no longer in the map would emit edges from a source absent from $serviceIds.
            if (!isset($this->descriptors[$id])) {
                continue;
            }

            $sourceId = DescriptorId::display($id);

            foreach ($this->edgeResolver->resolve($plan) as $edge) {
                $edges[] = new DependencyGraphEdge(
                    $sourceId,
                    DescriptorId::display($edge->targetId),
                    // A lazy dependency is still a dependency: resolution fails without it, it is merely constructed
                    // later. Only the validator's construction-order checks care about the difference.
                    required: $edge->required,
                    injectionPoint: $edge->injectionPoint,
                );
            }
        }

        return new DependencyGraph($serviceIds, $edges);
    }
}
