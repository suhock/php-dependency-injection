<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Compiler;

use Suhock\DependencyInjection\Container;
use Suhock\DependencyInjection\Descriptor;
use Suhock\DependencyInjection\Validation\ContainerValidationException;

/**
 * Turns a set of service descriptors into a {@see Container}.
 *
 * @internal
 */
interface ContainerCompilerInterface
{
    /**
     * Compiles the descriptors into an immutable container, validating that every service is resolvable.
     *
     * @param array<string, Descriptor<object>> $descriptors The service descriptors, keyed by descriptor id
     *
     * @throws ContainerValidationException If the configuration contains any guaranteed-failure defect
     */
    public function compile(array $descriptors): Container;

    /**
     * Exports the dependency graph {@see compile()} would produce.
     *
     * @param array<string, Descriptor<object>> $descriptors The service descriptors, keyed by descriptor id
     */
    public function exportGraph(array $descriptors): DependencyGraph;
}
