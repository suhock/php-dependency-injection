<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\Injector;
use UnitEnum;

/**
 * Reports the concrete class a container would resolve a service to, determined statically from its configuration
 * without constructing anything. A container that compiles a resolution plan can answer this; a container that only
 * knows how to fetch instances cannot, so this is a capability separate from {@see ContainerInterface}.
 *
 * It lets consumers such as the {@see Injector} build a native lazy object for an interface-typed dependency &mdash;
 * which requires a concrete class up front &mdash; without eagerly resolving it.
 */
interface ConcreteClassNameProviderInterface
{
    /**
     * The concrete class the container would produce for the given service, resolved statically without constructing
     * anything, or <code>null</code> when it cannot be determined statically (for example a factory with no concrete
     * declared return type, a context-dependent selector, or a service that is not configured).
     *
     * @param class-string $className The service to report the concrete class of
     * @param string|UnitEnum|null $key The key the service was added under, if any
     *
     * @return class-string|null
     */
    public function getConcreteClassName(string $className, string|UnitEnum|null $key = null): ?string;
}
