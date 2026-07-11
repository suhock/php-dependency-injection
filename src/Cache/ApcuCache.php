<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Cache;

use RuntimeException;

use function apcu_enabled;
use function apcu_fetch;
use function apcu_store;
use function extension_loaded;

/**
 * A {@see CacheInterface} backed by the APCu extension. Entries live in shared memory, so cached metadata persists
 * across requests served by the same PHP worker pool. Requires the <code>apcu</code> extension to be loaded and enabled
 * — including on the CLI, where <code>apc.enable_cli</code> must be set.
 */
final class ApcuCache implements CacheInterface
{
    /**
     * @throws RuntimeException If the APCu extension is not loaded and enabled in the current environment
     */
    public function __construct()
    {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            throw new RuntimeException('The APCu extension must be loaded and enabled to use ' . self::class);
        }
    }

    /**
     * @inheritDoc
     *
     * Uses the success flag from {@see apcu_fetch()} to distinguish a stored <code>false</code> from a cache miss.
     */
    public function tryGet(string $id, mixed &$value): bool
    {
        $value = apcu_fetch($id, $success);

        return (bool)$success;
    }

    /**
     * @inheritDoc
     */
    public function set(string $id, mixed $value): void
    {
        apcu_store($id, $value);
    }
}
