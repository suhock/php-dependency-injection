<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use Suhock\DependencyInjection\Descriptor\Descriptor;

/**
 * The compiled resolution shape of one service {@see Descriptor}: every dependency edge the runtime will attempt to
 * satisfy, plus the defects that guarantee a resolution failure. Produced by {@see ResolutionPlanFactory} from
 * reflection and the provider's dependency source, without instantiating anything. Immutable and free of reflection
 * objects, so plans are cheap to cache and share.
 *
 * @internal
 */
final class ResolutionPlan
{
    /**
     * @param class-string $className The class the provider produces
     * @param list<ResolutionPlanEdge> $edges The dependency edges resolution will attempt, in resolution order
     * @param bool $opaque Whether the provider is not introspectable — a trusted leaf whose dependencies cannot be
     * compiled
     * @param string|null $nonInstantiableMessage Why the autowired class can never be instantiated, if it cannot
     * @param list<string> $invalidInjectMemberMessages Defects in the class's #[Inject] members that make plan
     * computation itself throw; when present, member edges are omitted
     * @param string|null $declaredFactoryReturnType The factory's declared return class, when it declares a single
     * existing class or interface
     */
    public function __construct(
        public readonly string $className,
        public readonly array $edges,
        public readonly bool $opaque = false,
        public readonly ?string $nonInstantiableMessage = null,
        public readonly array $invalidInjectMemberMessages = [],
        public readonly ?string $declaredFactoryReturnType = null
    ) {
    }
}
