<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Cache;

use function array_key_exists;

/**
 * Two-tier get-or-compute memoization of reflected metadata: a request-local (L1) cache backed by an optional shared,
 * potentially cross-request (L2) {@see CacheInterface}. Callers store only immutable, reflection-free values
 * (method-name lists, dependency descriptors, or sentinels), so an L2 hit is a cheap fetch rather than an object-graph
 * unserialization.
 *
 * @internal
 */
final class MetadataCache
{
    /** @var array<string, mixed> */
    private array $inProcess = [];

    public function __construct(
        private readonly ?CacheInterface $shared = null,
    ) {}

    /**
     * Returns the value stored under the id, computing and populating both tiers on a miss.
     *
     * @template T
     *
     * @param callable():T $factory
     *
     * @return T
     */
    public function get(string $id, callable $factory): mixed
    {
        if (array_key_exists($id, $this->inProcess)) {
            /** @var T $cached */
            $cached = $this->inProcess[$id];

            return $cached;
        }

        if ($this->shared === null || !$this->shared->tryGet($id, $value)) {
            $value = $factory();
            $this->shared?->set($id, $value);
        }

        $this->inProcess[$id] = $value;

        /** @var T $value */
        return $value;
    }
}
