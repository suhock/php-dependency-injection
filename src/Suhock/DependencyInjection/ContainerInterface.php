<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use UnitEnum;

/**
 * Interface for retrieving dependencies from a container.
 */
interface ContainerInterface
{
    /**
     * Retrieves an object or <code>null</code> from the container identified by its class name.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified class name of the service to retrieve.
     * @param string|UnitEnum|null $key [optional] The key of the service to retrieve.
     *
     * @return TClass An instance of {@see $className}
     * @throws ClassNotFoundException If the container could not resolve a value for the specified class
     */
    public function get(string $className, string|UnitEnum|null $key = null): object;

    /**
     * Indicates whether the container can provide a value for a given class name. A <code>true</code> return value
     * <em>does not</em> indicate that {@see get()} will not throw an error while attempting to provide an instance.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The class name of the service to test for.
     * @param string|UnitEnum|null $key [optional] The key of the service to test for.
     *
     * @return bool <code>true</code> if the container can provide a value, <code>false</code> otherwise
     */
    public function has(string $className, string|UnitEnum|null $key = null): bool;
}
