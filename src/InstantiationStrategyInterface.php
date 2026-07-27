<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Instantiation;

use Suhock\DependencyInjection\Injector;
use Suhock\DependencyInjection\InjectorException;

/**
 * A strategy for constructing an instance of a class, injecting its constructor dependencies. The {@see Injector} tries
 * its strategies in order until one applies. Supply a custom ordered list to the injector to add, replace, or reorder
 * instantiation behavior.
 */
interface InstantiationStrategyInterface
{
    /**
     * Attempts to instantiate the class. Returns <code>null</code> if this strategy does not apply to the class, so the
     * injector can fall through to the next strategy.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The name of the class to instantiate
     * @param array<mixed> $params Explicit parameter values, matched by position then by name
     *
     * @throws InjectorException If the strategy applies but instantiation fails
     *
     * @return TClass|null A new instance, or <code>null</code> if this strategy does not apply
     */
    public function tryInstantiate(string $className, array $params): ?object;
}
