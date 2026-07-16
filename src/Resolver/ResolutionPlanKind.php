<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

/**
 * How a compiled {@see ResolutionPlan} produces its instance.
 *
 * @internal
 */
enum ResolutionPlanKind
{
    /**
     * Construct the class, injecting constructor arguments and the mutator's arguments.
     */
    case AutowiredClass;

    /**
     * Invoke the factory closure with resolved arguments and type-check the result.
     */
    case Factory;

    /**
     * Resolve the implementation target from the container.
     */
    case Implementation;

    /**
     * No dependencies. Ask the instance provider directly.
     */
    case Leaf;
}
