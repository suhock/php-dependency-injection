<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\Disposable\DisposableInterface;

/**
 * Interface for a scope: a resolution root with a bounded lifetime, created by a {@see ScopeFactoryInterface}. A scope
 * resolves the same services as the container that created it, but services added with a scoped lifetime are
 * instantiated once per scope, and their dependencies are resolved from the scope rather than the root container.
 */
interface ScopeInterface extends ContainerInterface, DisposableInterface
{
    /**
     * Ends the scope. Container-owned disposable instances cached by the scope are disposed, dependents before their
     * dependencies, and all cached instances are released. Any further request to the scope throws a
     * {@see ScopeException}. Disposing an already disposed scope has no effect.
     */
    public function dispose(): void;
}
