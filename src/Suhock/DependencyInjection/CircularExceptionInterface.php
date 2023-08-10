<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Throwable;

/**
 * Interface for exceptions that indicate a dependency could not be resolved because it contains a circular dependency.
 *
 * @template TClass of object
 */
interface CircularExceptionInterface extends Throwable
{
    /**
     * @return class-string<TClass> The class name of the dependency that could not be resolved due to circular
     * dependency
     */
    public function getClassName(): string;
}
