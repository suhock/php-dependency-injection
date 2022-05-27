<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

/**
 * Provides methods for injecting dependencies into function and constructor calls.
 */
interface InjectorInterface
{
    /**
     * Calls the specified function, injecting any function parameter values.
     *
     * @param callable $function The function to call
     * @param array $params [optional] A list of parameter values to provide to the function. String keys will be
     * matched by name; integer keys will be matched by position.
     *
     * @return mixed The value returned by the function
     * @throws DependencyInjectionException If there was an error resolving values for the function parameters or
     * invoking the function
     */
    public function call(callable $function, array $params = []): mixed;

    /**
     * Creates a new instance of the specified class, injecting any constructor parameter values.
     *
     * @template T
     *
     * @param class-string<T> $className The name of the class to instantiate
     * @param array $params [optional] A list of parameter values to provide to the constructor. String keys will be
     * matched by name; integer keys will be matched by position.
     *
     * @return T A new instance of the specified class
     * @throws DependencyInjectionException If there was an error resolving values for the constructor parameters or
     * invoking the constructor
     */
    public function instantiate(string $className, array $params = []): object;
}
