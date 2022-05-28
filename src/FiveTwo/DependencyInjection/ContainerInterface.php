<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

interface ContainerInterface
{
    /**
     * Retrieves an object or <code>null</code> from the container identified by its class name.
     *
     * @template TDependency
     *
     * @param class-string<TDependency> $className The name of the class to retrieve
     *
     * @return TDependency|null An instance of {@see $className} or <code>null</code>
     * @throws UnresolvedClassException If the container could not resolve a value for the specified class
     */
    public function get(string $className): ?object;

    /**
     * Indicates whether the container can provide a value (include <code>null</code>) for a give class name. This
     * <em>does not</em> indicate whether the {@see get()} method will not throw an error when attempting to retrieve an
     * object.
     *
     * @template TDependency
     *
     * @param class-string<TDependency> $className The name of the class to test
     *
     * @return bool <code>true</code> if the container can provide a value, <code>false</code> otherwise
     */
    public function has(string $className): bool;
}
