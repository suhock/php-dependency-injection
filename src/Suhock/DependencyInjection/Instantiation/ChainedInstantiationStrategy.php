<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Instantiation;

/**
 * Composite {@see InstantiationStrategyInterface} that delegates to an ordered list of member strategies, returning the
 * first non-<code>null</code> result. It declines (returns <code>null</code>) only when every member declines, so it
 * composes cleanly — a chain may itself be a member of another chain.
 */
final class ChainedInstantiationStrategy implements InstantiationStrategyInterface
{
    /** @var list<InstantiationStrategyInterface> */
    private readonly array $strategies;

    /**
     * @param list<InstantiationStrategyInterface> $strategies Ordered strategies, each tried in turn until one applies
     */
    public function __construct(array $strategies)
    {
        $this->strategies = $strategies;
    }

    public function tryInstantiate(string $className, array $params): ?object
    {
        foreach ($this->strategies as $strategy) {
            $instance = $strategy->tryInstantiate($className, $params);

            if ($instance !== null) {
                return $instance;
            }
        }

        return null;
    }
}
