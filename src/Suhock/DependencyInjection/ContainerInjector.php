<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

/**
 * Injects dependencies resolved from a container.
 */
class ContainerInjector extends Injector
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct(new ContainerParameterResolver($container));
    }
}