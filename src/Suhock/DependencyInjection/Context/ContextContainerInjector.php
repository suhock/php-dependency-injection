<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Context;

use Suhock\DependencyInjection\Injector;

/**
 * Injects dependencies resolved from a {@see ContextContainer}.
 *
 * @template TContainer of \Suhock\DependencyInjection\ContainerInterface
 */
class ContextContainerInjector extends Injector
{
    /**
     * @param ContextContainer<TContainer> $container
     */
    public function __construct(ContextContainer $container)
    {
        parent::__construct(new ContextContainerParameterResolver($container));
    }
}
