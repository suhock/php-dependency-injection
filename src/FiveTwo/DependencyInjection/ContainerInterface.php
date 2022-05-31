<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

interface ContainerInterface
{
    /**
     * Retrieves an object or <code>null</code> from the container identified by its class name.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The name of the class to retrieve
     *
     * @return TClass|null An instance of {@see $className} or <code>null</code>
     * @throws UnresolvedClassException If the container could not resolve a value for the specified class
     */
    public function get(string $className): ?object;

    /**
     * Indicates whether the container can provide a value (include <code>null</code>) for a give class name. This
     * <em>does not</em> indicate whether the {@see get()} method will not throw an error when attempting to retrieve an
     * object.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The name of the class to test
     *
     * @return bool <code>true</code> if the container can provide a value, <code>false</code> otherwise
     */
    public function has(string $className): bool;
}
