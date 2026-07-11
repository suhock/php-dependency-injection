<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

/**
 * Interface for classes that create scopes. Services that need to open scopes of their own — a queue worker creating a
 * scope per message, for example — should depend on this interface rather than on the container itself.
 */
interface ScopeFactoryInterface
{
    /**
     * Creates a new scope. The caller controls the scope's lifetime and should call {@see ScopeInterface::dispose()}
     * when the unit of work the scope represents has ended.
     */
    public function createScope(): ScopeInterface;
}
