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

/**
 * Interface for building a dependency container.
 */
interface ContainerBuilder extends
    ContainerBuilderInterface,
    ContainerSingletonBuilderInterface,
    ContainerTransientBuilderInterface
{
    /**
     * Allows a callback function to build on this {@see ContainerBuilder} instance. Useful for separating logic for
     * constructing dependencies into the respective modules in which the dependencies exist.
     *
     * @param callable(static):void $builder A callback taking this {@see ContainerBuilder} instance as an argument
     *
     * @return $this
     */
    public function build(callable $builder): static;
}
