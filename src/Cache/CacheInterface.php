<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Cache;

/**
 * A minimal key/value store used to memoize reflected metadata. Values are treated as opaque — an implementation must
 * return what it was given, unchanged — so any type may be stored, including <code>null</code> and <code>false</code>.
 * The {@see Injector} pairs an implementation of this (as a shared, potentially cross-request cache) with its own
 * in-process cache; a persistent implementation such as {@see ApcuCache} lets reflected metadata survive between
 * requests.
 */
interface CacheInterface
{
    /**
     * Looks up the value stored under the given id. Presence is reported by the return value rather than by throwing,
     * so a stored value that is itself <code>null</code> or <code>false</code> is not mistaken for a miss.
     *
     * @param string $id The cache key
     * @param mixed $value Assigned the stored value when the id is present; left indeterminate otherwise
     *
     * @return bool <code>true</code> if the id was present (and <code>$value</code> was populated), <code>false</code>
     * otherwise
     */
    public function tryGet(string $id, mixed &$value): bool;

    /**
     * Stores a value under the given id, overwriting any existing entry.
     *
     * @param string $id The cache key
     * @param mixed $value The value to store
     */
    public function set(string $id, mixed $value): void;
}
