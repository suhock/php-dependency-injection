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
 * Interface for services that hold resources which must be released when their resolution root's lifetime ends. A
 * resolution root (the container or a scope) disposes the disposable services it created — dependents before their
 * dependencies — when that root is itself disposed. Instances supplied by the caller (see
 * {@see Builder\ContainerSingletonBuilderInterface::addSingletonInstance()}) are owned by the caller and are never
 * disposed by the container.
 */
interface DisposableInterface
{
    /**
     * Releases the resources held by the object. Disposing an already disposed object must have no effect.
     */
    public function dispose(): void;
}
