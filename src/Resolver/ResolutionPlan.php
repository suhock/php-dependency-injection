<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use Suhock\DependencyInjection\Builder\Descriptor;

/**
 * The compiled resolution shape of one service {@see Descriptor}: how the instance is produced, every dependency edge
 * resolution will satisfy, and the defects that guarantee a resolution failure. Produced by
 * {@see ResolutionPlanFactory} from reflection and the provider's dependency source, without instantiating anything.
 * Immutable and free of reflection objects and closures, so plans are cheap to cache and share; the executable parts
 * (factory and mutator closures) stay on the descriptor's provider and are paired with the plan at execution time.
 *
 * @internal
 */
final class ResolutionPlan
{
    /**
     * @param class-string $className The class the plan produces
     * @param ResolutionPlanKind $kind How the instance is produced
     * @param list<ResolutionPlanEdge> $argumentEdges Constructor arguments ({@see ResolutionPlanKind::AutowiredClass})
     * or factory arguments ({@see ResolutionPlanKind::Factory}), in call order
     * @param array<string, list<ResolutionPlanEdge>> $injectMethodEdges Per #[Inject] method, its parameter edges in
     * call order, keyed by method name
     * @param array<string, ResolutionPlanEdge> $injectPropertyEdges Per #[Inject] property, its edge, keyed by
     * property name
     * @param list<ResolutionPlanEdge> $mutatorEdges The mutator's parameter edges after the instance parameter, in
     * call order
     * @param class-string|null $implementationTarget The service the container resolves in this service's place
     * ({@see ResolutionPlanKind::Implementation})
     * @param string|null $nonInstantiableMessage Why the autowired class can never be instantiated, if it cannot
     * @param list<string> $invalidInjectMemberMessages Defects in the class's #[Inject] members that make plan
     * computation itself throw; when present, member edges are omitted
     * @param string|null $declaredFactoryReturnType The factory's declared return class, when it declares a single
     * existing class or interface
     */
    public function __construct(
        public readonly string $className,
        public readonly ResolutionPlanKind $kind,
        public readonly array $argumentEdges = [],
        public readonly array $injectMethodEdges = [],
        public readonly array $injectPropertyEdges = [],
        public readonly array $mutatorEdges = [],
        public readonly ?string $implementationTarget = null,
        public readonly ?string $nonInstantiableMessage = null,
        public readonly array $invalidInjectMemberMessages = [],
        public readonly ?string $declaredFactoryReturnType = null,
    ) {}
}
