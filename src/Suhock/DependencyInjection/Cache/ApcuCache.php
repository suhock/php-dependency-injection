<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Cache;

use OutOfBoundsException;

class ApcuCache implements CacheInterface
{
    public function get(string $id): mixed
    {
        return $this->has($id) ?
            apcu_fetch($id) :
            throw new OutOfBoundsException("Key does not exist in cache: $id");
    }

    public function has(string $id): bool
    {
        return apcu_exists($id);
    }
}
