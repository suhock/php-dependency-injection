<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

use Override;
use Suhock\DependencyInjection\Cache\CacheInterface;

use function array_key_exists;
use function array_keys;
use function str_starts_with;

/**
 * Fakes a shared cache with an in-memory array, recording every store for assertions.
 */
final class FakeCache implements CacheInterface
{
    /** @var array<string, mixed> */
    public array $values = [];

    /** @var list<string> */
    public array $storedIds = [];

    #[Override]
    public function tryGet(string $id, mixed &$value): bool
    {
        if (!array_key_exists($id, $this->values)) {
            return false;
        }

        $value = $this->values[$id];

        return true;
    }

    #[Override]
    public function set(string $id, mixed $value): void
    {
        $this->values[$id] = $value;
        $this->storedIds[] = $id;
    }

    /**
     * @return list<string> The cached ids starting with the given prefix
     */
    public function idsWithPrefix(string $prefix): array
    {
        $ids = [];

        foreach (array_keys($this->values) as $id) {
            if (str_starts_with($id, $prefix)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
