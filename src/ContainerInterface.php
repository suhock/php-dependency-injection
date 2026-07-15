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
 * Interface for retrieving services from a container.
 */
interface ContainerInterface
{
    /**
     * Retrieves a service from the container, identified by its class name.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified class name of the service to retrieve.
     * @param string|UnitEnum|null $key [optional] The key of the service to retrieve.
     *
     * @throws ClassNotFoundException If the container could not resolve a service for the specified class
     *
     * @return TClass An instance of {@see $className}
     */
    public function get(string $className, string|UnitEnum|null $key = null): object;

    /**
     * Indicates whether the container can provide a service for a given class name. A <code>true</code> return value
     * <em>does not</em> indicate that {@see get()} will not throw an error while attempting to provide an instance.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The class name of the service to test for.
     * @param string|UnitEnum|null $key [optional] The key of the service to test for.
     *
     * @return bool <code>true</code> if the container can provide a service, <code>false</code> otherwise
     */
    public function has(string $className, string|UnitEnum|null $key = null): bool;
}
