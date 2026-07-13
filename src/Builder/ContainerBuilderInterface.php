<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use UnitEnum;

/**
 * Interface for building a dependency container.
 */
interface ContainerBuilderInterface extends ContainerSingletonBuilderInterface, ContainerScopedBuilderInterface,
    ContainerTransientBuilderInterface
{
    /**
     * Removes the specified service and any instance cached by this container, if they exist. Services of the same
     * class added under other keys are unaffected.
     *
     * Removal releases the container's cached instance without disposing it; an instance still referenced elsewhere
     * remains eligible for disposal when the container is disposed.
     *
     * @param class-string $className The class name of the service to remove
     * @param string|UnitEnum|null $key [optional] The key of the service to remove, or null for the unkeyed service
     *
     * @return $this
     */
    public function remove(string $className, string|UnitEnum|null $key = null): static;

    /**
     * @template TBuilder of self
     *
     * @param callable(TBuilder):mixed $configure
     *
     * @return $this
     */
    public function configure(callable $configure): static;
}
