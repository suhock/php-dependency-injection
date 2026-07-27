<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Compiler;

use Suhock\DependencyInjection\Builder\Descriptor;

/**
 * The compiled resolution shape of one service {@see Descriptor}: how the instance is produced, every dependency edge
 * resolution will satisfy, and the defects that guarantee a resolution failure. Produced by
 * {@see ResolutionPlanFactory} from reflection and the provider's dependency source, without instantiating anything.
 * The executable part (the factory closure) stays on the descriptor's provider and is paired with the plan at execution
 * time.
 *
 * @internal
 */
final class ResolutionPlan
{
    /**
     * @param class-string $className The class the plan produces
     * @param ResolutionPlanKind $kind How the instance is produced
     * @param list<ResolutionPlanEdge> $argumentEdges Constructor arguments ({@see ResolutionPlanKind::AutowiredClass})
     *     or factory arguments ({@see ResolutionPlanKind::Factory}), in call order
     * @param list<ResolutionPlanEdge> $selfConstructorEdges The constructor arguments of the service's own class, in
     *     call order, when a factory parameter names the service the factory produces
     *     ({@see ResolutionPlanEdge::$self}); empty otherwise
     * @param class-string|null $implementationTarget The service the container resolves in this service's place
     *     ({@see ResolutionPlanKind::Implementation})
     * @param string|null $nonInstantiableMessage Why the autowired class can never be instantiated, if it cannot
     * @param string|null $declaredFactoryReturnType The factory's declared return class, when it declares a single
     *     existing class or interface
     */
    public function __construct(
        public readonly string $className,
        public readonly ResolutionPlanKind $kind,
        public readonly array $argumentEdges = [],
        public readonly array $selfConstructorEdges = [],
        public readonly ?string $implementationTarget = null,
        public readonly ?string $nonInstantiableMessage = null,
        public readonly ?string $declaredFactoryReturnType = null,
    ) {}
}
