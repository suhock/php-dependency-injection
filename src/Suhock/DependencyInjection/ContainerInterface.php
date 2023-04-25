<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022-2023 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

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
     * @param class-string<TClass> $className The name of the class to retrieve
     *
     * @return TClass An instance of {@see $className}
     * @throws ClassNotFoundException If the container could not resolve a value for the specified class
     */
    public function get(string $className): object;

    /**
     * Indicates whether the container can provide a value for a given class name. A <code>true</code> return value
     * <em>does not</em> indicate that {@see get()} will not throw an error while attempting to provide an instance.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The name of the class to test
     *
     * @return bool <code>true</code> if the container can provide a value, <code>false</code> otherwise
     * @psalm-mutation-free
     */
    public function has(string $className): bool;
}
