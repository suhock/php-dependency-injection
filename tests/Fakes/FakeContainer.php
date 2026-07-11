<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

use Suhock\DependencyInjection\ClassNotFoundException;
use Suhock\DependencyInjection\ContainerInterface;
use UnitEnum;
use function array_key_exists;

/**
 * Fakes a simple container.
 */
final class FakeContainer implements ContainerInterface
{
    /**
     * @param array<callable> $classMapping
     * @phpstan-param array<class-string, callable():object> $classMapping
     */
    public function __construct(
        public array $classMapping = []
    ) {
    }

    public function get(string $className, string|UnitEnum|null $key = null): object
    {
        if (!$this->has($className)) {
            throw new ClassNotFoundException($className);
        }

        /** @phpstan-ignore offsetAccess.notFound, return.type (test fake does not model array class mappings) */
        return ($this->classMapping[$className])();
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $className The name of the class to test
     */
    public function has(string $className, string|UnitEnum|null $key = null): bool
    {
        return array_key_exists($className, $this->classMapping);
    }
}
