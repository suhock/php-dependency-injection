<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Cache;

use function array_key_exists;

class MemoryCache implements CacheInterface
{
    private array $cache = [];

    public function get(string $id): mixed
    {
        return $this->has($id) ?
            $this->cache[$id] :
            throw new \OutOfBoundsException("Key not found in cache: $id");
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->cache);
    }
}
