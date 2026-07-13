<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\InstanceProvider;

/**
 * Marker for the construction-shape description returned by
 * {@see IntrospectableInstanceProviderInterface::getDependencySource()}. Resolution-agnostic: it describes how a
 * provider constructs its instance, not what the instance resolves to, so a validator can derive dependency graph
 * edges from it by reflecting rather than by instantiating anything.
 *
 * This is a closed set by convention. The only implementations are {@see AutowireClassSource}, {@see CallableSource},
 * {@see ReferenceSource}, and {@see LeafSource}.
 */
interface DependencySource
{
}
