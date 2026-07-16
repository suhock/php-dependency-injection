<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

/**
 * Provides methods for injecting dependencies into function and constructor calls.
 */
interface InjectorInterface
{
    /**
     * Calls the specified function, injecting any function parameter values.
     *
     * @param callable $function The function to call
     * @param array<mixed> $params [optional] A list of parameter values to provide to the function. String keys will be
     *     matched by name; integer keys will be matched by position.
     *
     * @throws InjectorException If there was an error resolving values for the function parameters or invoking the
     *     function
     *
     * @return mixed The value returned by the function
     */
    public function call(callable $function, array $params = []): mixed;

    /**
     * Creates a new instance of the specified class, injecting any constructor parameter values.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The name of the class to instantiate
     * @param array<mixed> $params [optional] A list of parameter values to provide to the constructor. String keys will
     *     be matched by name; integer keys will be matched by position.
     *
     * @throws InjectorException If there was an error resolving values for the constructor parameters or invoking the
     *     constructor
     *
     * @return TClass A new instance of the specified class
     */
    public function instantiate(string $className, array $params = []): object;
}
